<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class SeoSettings extends Settings
{
    public string $site_title;

    public string $default_description;

    public ?string $default_og_image_path;

    /** Cloudflare Web Analytics beacon token; the beacon loads only in production when it is set. */
    public ?string $analytics_token = null;

    public static function group(): string
    {
        return 'seo';
    }
}
