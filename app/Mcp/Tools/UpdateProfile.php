<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ChangeSummary;
use App\Settings\ProfileSettings;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-profile')]
#[Description('Edit the public profile: name, title, tagline, about (Markdown), public location, contact email, social links. Payment, invoicing and security settings are not available.')]
final class UpdateProfile extends ScopedTool
{
    public static function scope(): string
    {
        return 'settings:write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string(),
            'title' => $schema->string(),
            'tagline' => $schema->string(),
            'about_md' => $schema->string()->description('Markdown'),
            'location_public' => $schema->string()->description('City and country only, never a street address'),
            'email' => $schema->string(),
            'socials' => $schema->array()->description('List of {"network": "github|linkedin|x|email", "url": "https://… or mailto:…"}'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'title' => ['sometimes', 'string', 'max:120'],
            'tagline' => ['sometimes', 'string', 'max:300'],
            'about_md' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'location_public' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'socials' => ['sometimes', 'array', 'max:6'],
            'socials.*.network' => ['required', 'in:github,linkedin,x,email'],
            'socials.*.url' => ['required', 'string', 'max:255', 'regex:#^(https://|mailto:)#'],
        ]);
        if ($data === []) {
            return Response::error('Nothing to change.');
        }

        $profile = app(ProfileSettings::class);
        $before = [];
        foreach ($data as $field => $value) {
            $before[$field] = $profile->{$field};
            $profile->{$field} = $value;
        }
        if (array_key_exists('about_md', $data)) {
            $profile->about_html = app(MarkdownRenderer::class)->render((string) $data['about_md']);
        }
        $profile->save();
        $this->audit('settings', 'profile', 'Profile', ChangeSummary::diff($before, $data));

        return Response::text('Profile saved: '.implode(', ', array_keys($data)).'.');
    }
}
