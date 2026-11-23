<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class BrandingSettings extends Settings
{
    public ?string $logo_path;

    public ?string $admin_logo_path;

    public ?string $admin_logo_dark_path;

    public ?string $favicon_path;

    public ?string $apple_touch_path;

    public ?string $og_logo_path;

    public static function group(): string
    {
        return 'branding';
    }
}
