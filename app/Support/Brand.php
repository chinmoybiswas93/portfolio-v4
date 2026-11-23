<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\ProfileSettings;

/**
 * Resolves branding: Settings (database) first, then config/brand.php.
 */
final class Brand
{
    public static function name(): string
    {
        return self::filled(app(ProfileSettings::class)->name) ?? (string) config('brand.site_name');
    }

    public static function email(): string
    {
        return self::filled(app(ProfileSettings::class)->email) ?? (string) config('brand.contact_email');
    }

    public static function domain(): string
    {
        return (string) config('brand.domain');
    }

    public static function adminName(): string
    {
        return (string) config('brand.admin_name');
    }

    private static function filled(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
