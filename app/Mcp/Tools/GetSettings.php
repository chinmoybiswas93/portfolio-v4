<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Settings\ProfileSettings;
use App\Settings\SeoSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-settings')]
#[Description('Read the profile or SEO settings. Payment, invoicing and security settings are not available.')]
final class GetSettings extends ScopedTool
{
    public static function scope(): string
    {
        return 'settings:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['group' => $schema->string()->enum(['profile', 'seo'])->required()];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['group' => ['required', 'in:profile,seo']]);
        $this->audit('settings', $data['group']);

        if ($data['group'] === 'seo') {
            $seo = app(SeoSettings::class);

            return Response::json(['site_title' => $seo->site_title, 'default_description' => $seo->default_description]);
        }

        $profile = app(ProfileSettings::class);

        return Response::json([
            'name' => $profile->name,
            'title' => $profile->title,
            'tagline' => $profile->tagline,
            'about_md' => $profile->about_md,
            'location_public' => $profile->location_public,
            'email' => $profile->email,
            'socials' => $profile->socials,
        ]);
    }
}
