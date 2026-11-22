<?php

declare(strict_types=1);

use App\Models\McpAuditLog;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Settings\ProfileSettings;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['mcp-server.enabled' => true]);
    Storage::fake('public');
});

function asMcp(array $scopes): void
{
    Passport::actingAs(adminWithMfa(), ['mcp:use', ...$scopes], 'api');
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>|string
 */
function callTool(string $name, array $arguments, int $expectStatus = 200): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]]);
    $response->assertStatus($expectStatus);
    $text = (string) $response->json('result.content.0.text');
    $decoded = json_decode($text, true);

    return is_array($decoded) ? $decoded + ['_isError' => (bool) $response->json('result.isError')] : $text;
}

it('creates a draft post from Markdown with tags and a category, and audits the change', function (): void {
    asMcp(['content:write']);

    $saved = callTool('save-content', ['type' => 'post', 'fields' => [
        'title' => 'Speeding up WordPress', 'body_md' => "## Measure first\n\nUse **real** numbers.", 'status' => 'draft',
        'tags' => ['Performance', 'WordPress'], 'category' => 'WordPress',
    ]]);

    $post = Post::query()->with(['tags', 'category'])->findOrFail($saved['id']);
    expect($saved['saved'])->toBe('created')->and($post->slug)->toBe('speeding-up-wordpress')
        ->and($post->status->value)->toBe('draft')->and($post->published_at)->toBeNull()
        ->and($post->body_html)->toContain('<strong>real</strong>')
        ->and($post->tags->pluck('name')->sort()->values()->all())->toBe(['Performance', 'WordPress'])
        ->and($post->category?->name)->toBe('WordPress');

    $log = McpAuditLog::query()->where('tool', 'save-content')->firstOrFail();
    expect($log->target_type)->toBe('post')->and($log->changes['action'])->toBe('created')->and($log->changes['fields'])->toHaveKey('title');
});

it('updates only the given fields and refuses HTML and unknown fields', function (): void {
    asMcp(['content:write']);
    $project = Project::factory()->create(['title' => 'Old', 'summary' => 'Keep me']);

    $saved = callTool('save-content', ['type' => 'project', 'id' => $project->id, 'fields' => ['title' => 'New title', 'status' => 'draft']]);
    expect($saved['title'])->toBe('New title')->and($project->refresh()->summary)->toBe('Keep me');

    $refused = callTool('save-content', ['type' => 'post', 'fields' => ['title' => 'X', 'body_md' => 'x', 'body_html' => '<script>alert(1)</script>']]);
    expect($refused)->toContain('cannot be set: body_html');
});

it('escapes raw HTML written as Markdown', function (): void {
    asMcp(['content:write']);

    $saved = callTool('save-content', ['type' => 'post', 'fields' => ['title' => 'XSS', 'body_md' => "<script>alert(1)</script>\n\n[x](javascript:alert(1))"]]);

    $html = (string) Post::query()->findOrFail($saved['id'])->body_html;
    expect($html)->not->toContain('<script>')->not->toContain('javascript:');
});

it('reports validation errors without saving', function (): void {
    asMcp(['content:write']);

    expect(callTool('save-content', ['type' => 'post', 'fields' => ['title' => 'No body']]))->toContain('body md field is required');
    expect(Post::query()->count())->toBe(0);
});

it('moves content to the trash and back; trashed items are not public', function (): void {
    asMcp(['content:trash']);
    $post = Post::factory()->create(['slug' => 'live']);
    $service = Service::factory()->create(['title' => 'Care plans']);

    expect(callTool('trash-content', ['type' => 'post', 'id' => $post->id, 'action' => 'trash']))->toContain('Moved to the trash');
    callTool('trash-content', ['type' => 'service', 'id' => $service->id, 'action' => 'trash']);

    expect(Post::query()->find($post->id))->toBeNull()->and(Post::withTrashed()->find($post->id))->not->toBeNull();
    $this->get('/blog/live')->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee('/blog/live');
    $this->get('/')->assertDontSee('Care plans');

    callTool('trash-content', ['type' => 'post', 'id' => $post->id, 'action' => 'restore']);
    $this->get('/blog/live')->assertOk();
});

it('has no tool that deletes permanently', function (): void {
    asMcp(['content:read', 'content:write', 'content:trash', 'seo:write', 'media:upload', 'settings:write']);

    $names = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'))->pluck('name');
    expect($names->filter(fn (string $n): bool => str_contains($n, 'delete') || str_contains($n, 'destroy') || str_contains($n, 'purge')))->toBeEmpty()
        ->and($names->all())->toContain('trash-content', 'save-content', 'upload-image', 'update-seo', 'update-profile');
});

it('edits SEO fields and site defaults with seo:write', function (): void {
    asMcp(['seo:write']);
    $post = Post::factory()->create();

    callTool('update-seo', ['target' => 'post', 'id' => $post->id, 'seo_title' => 'Faster WordPress', 'seo_description' => 'How to measure.']);
    callTool('update-seo', ['target' => 'site', 'site_title' => 'Chinmoy Biswas', 'default_description' => 'WordPress and Laravel.']);

    expect($post->refresh()->seo_title)->toBe('Faster WordPress')->and(app(SeoSettings::class)->default_description)->toBe('WordPress and Laravel.');
});

it('edits the profile but nothing else with settings:write', function (): void {
    asMcp(['settings:write']);

    callTool('update-profile', ['tagline' => 'New tagline', 'about_md' => '**Hi**']);
    $profile = app(ProfileSettings::class);
    expect($profile->tagline)->toBe('New tagline')->and($profile->about_html)->toContain('<strong>Hi</strong>');

    expect(callTool('update-profile', ['bank_details' => 'X']))->toContain('Nothing to change');
});

it('refuses write tools without their scope', function (): void {
    asMcp(['content:read']);

    callTool('save-content', ['type' => 'post', 'fields' => ['title' => 'X', 'body_md' => 'x']], 403);
    callTool('trash-content', ['type' => 'post', 'id' => 1, 'action' => 'trash'], 403);
    callTool('upload-image', ['target' => 'post', 'id' => 1, 'slot' => 'cover', 'data_base64' => 'x', 'alt' => 'x'], 403);
    callTool('update-profile', ['tagline' => 'x'], 403);
});

/** A small JPEG with an EXIF (APP1) segment and a PHP payload appended after the image data. */
function jpegWithExifAndPayload(): string
{
    $img = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($img);
    $jpeg = (string) ob_get_clean();
    $exif = "Exif\x00\x00".'GPS 51.5N 0.12W secret-camera-serial';
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2).'<?php system($_GET["c"]); ?>';
}

it('uploads an image, strips metadata and payloads, and returns Markdown', function (): void {
    asMcp(['media:upload']);
    $post = Post::factory()->create();

    $result = callTool('upload-image', ['target' => 'post', 'id' => $post->id, 'slot' => 'inline', 'data_base64' => base64_encode(jpegWithExifAndPayload()), 'alt' => 'Speed chart']);

    $media = $post->refresh()->getFirstMedia('images');
    $stored = (string) Storage::disk('public')->get($media?->getPathRelativeToRoot() ?? '');
    expect($result['markdown'])->toStartWith('![Speed chart](')->and($media?->getCustomProperty('alt'))->toBe('Speed chart')
        ->and($stored)->not->toContain('secret-camera-serial')->not->toContain('<?php')->not->toContain('Exif');
});

it('rejects other file types, oversize images and URLs', function (): void {
    asMcp(['media:upload']);
    $project = Project::factory()->create();
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    expect(callTool('upload-image', ['target' => 'project', 'id' => $project->id, 'slot' => 'gallery', 'data_base64' => base64_encode($gif), 'alt' => 'x']))->toContain('Only JPEG, PNG and WebP');
    expect(callTool('upload-image', ['target' => 'project', 'id' => $project->id, 'slot' => 'gallery', 'data_base64' => 'https://example.com/a.jpg', 'alt' => 'x']))->toContain('valid base64');

    $big = imagecreatetruecolor(6001, 2);
    ob_start();
    imagepng($big);
    expect(callTool('upload-image', ['target' => 'project', 'id' => $project->id, 'slot' => 'gallery', 'data_base64' => base64_encode((string) ob_get_clean()), 'alt' => 'x']))->toContain('6000');
});
