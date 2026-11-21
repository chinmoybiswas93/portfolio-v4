<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class SeoSettings extends Settings
{
    public string $site_title;

    public string $default_description;

    public ?string $default_og_image_path;

    public static function group(): string
    {
        return 'seo';
    }
}
