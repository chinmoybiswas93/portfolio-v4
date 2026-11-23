<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use GdImage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('upload-image')]
#[Description('Upload an image from base64 file data (JPEG, PNG or WebP, up to 8 MB and 6000 px) and attach it to a post or project as its cover, a gallery image (projects) or an inline image (posts). Alt text is required. The image is re-encoded, which removes metadata. Returns the media id, URL and a Markdown snippet. URLs are not fetched.')]
final class UploadImage extends ScopedTool
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MAX_SIDE = 6000;

    /** @var array<string, string> MIME type => file extension */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function scope(): string
    {
        return 'media:upload';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'target' => $schema->string()->enum(['post', 'project'])->required(),
            'id' => $schema->integer()->required(),
            'slot' => $schema->string()->enum(['cover', 'gallery', 'inline'])->required()->description('gallery: projects only; inline: posts only (use the returned Markdown in body_md)'),
            'data_base64' => $schema->string()->required()->description('The file contents, base64-encoded'),
            'alt' => $schema->string()->required()->description('Describe the image for screen readers'),
            'filename' => $schema->string()->description('Optional name, e.g. shop-home.jpg'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'target' => ['required', 'in:post,project'],
            'id' => ['required', 'integer'],
            'slot' => ['required', 'in:cover,gallery,inline'],
            'data_base64' => ['required', 'string', 'max:'.(int) ceil(self::MAX_BYTES * 4 / 3 + 16)],
            'alt' => ['required', 'string', 'max:200'],
            'filename' => ['nullable', 'string', 'max:100'],
        ]);
        $collection = match ([$data['target'], $data['slot']]) {
            ['post', 'cover'], ['project', 'cover'] => 'cover',
            ['project', 'gallery'] => 'gallery',
            ['post', 'inline'] => 'images',
            default => null,
        };
        if ($collection === null) {
            return Response::error('Use "gallery" for projects and "inline" for posts.');
        }

        $model = ($data['target'] === 'post' ? Post::class : Project::class)::query()->find($data['id']);
        $user = $request->user();
        if ($model === null) {
            return Response::error('Not found.');
        }
        if (! $user instanceof User || Gate::forUser($user)->denies('update', $model)) {
            return Response::error('Not allowed.');
        }

        $bytes = base64_decode((string) $data['data_base64'], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return Response::error('The image must be valid base64 data, at most 8 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! is_string($mime) || ! isset(self::TYPES[$mime])) {
            return Response::error('Only JPEG, PNG and WebP images are accepted.');
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_SIDE || $size[1] > self::MAX_SIDE) {
            return Response::error('The image must be at most 6000 × 6000 px.');
        }

        // Decode and re-encode: drops EXIF/GPS and anything appended to the file.
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            return Response::error('The image could not be read.');
        }
        $ext = self::TYPES[$mime];
        // Private working file with a random name; the media library moves it into place.
        File::ensureDirectoryExists(storage_path('app/private/mcp-uploads'));
        $file = storage_path('app/private/mcp-uploads/'.Str::random(40).'.'.$ext);
        $ok = match ($ext) {
            'jpg' => imagejpeg($image, $file, 86),
            'png' => imagepng($image, $file, 6),
            'webp' => imagewebp($image, $file, 86),
        };
        if (! $ok) {
            @unlink($file);

            return Response::error('The image could not be stored.');
        }

        $base = Str::slug(pathinfo((string) ($data['filename'] ?? ''), PATHINFO_FILENAME)) ?: Str::slug(Str::limit((string) $data['alt'], 40, '')) ?: 'image';
        $media = $model->addMedia($file)
            ->usingFileName($base.'.'.$ext)
            ->withCustomProperties(['alt' => (string) $data['alt']])
            ->toMediaCollection($collection, 'public');

        $url = $media->getUrl();
        $this->audit($data['target'], $model->getKey(), (string) $model->getAttribute('title'), ['action' => 'image uploaded', 'slot' => $data['slot'], 'media_id' => $media->getKey()]);

        return Response::json([
            'media_id' => $media->getKey(),
            'url' => $url,
            'markdown' => '!['.str_replace(['[', ']'], '', (string) $data['alt']).']('.$url.')',
            'width' => $size[0],
            'height' => $size[1],
        ]);
    }
}
