<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\BrandingSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Logo, favicon, touch icon and share-image logo: an upload from Settings → Branding when there is one,
 * otherwise the bundled file.
 */
final class BrandAssets
{
    public static function logoUrl(string $fallback = 'brand/logo-mark.svg'): string
    {
        return self::url(app(BrandingSettings::class)->logo_path) ?? asset($fallback);
    }

    /** Admin header logo for the light theme (dark artwork). */
    public static function adminLogoUrl(): string
    {
        return self::url(app(BrandingSettings::class)->admin_logo_path) ?? asset('brand/logo-mark-dark.svg');
    }

    /** Admin header logo for the dark theme and the dark consent screens (light artwork). */
    public static function adminLogoDarkUrl(): string
    {
        return self::url(app(BrandingSettings::class)->admin_logo_dark_path) ?? asset('brand/logo-mark-light.svg');
    }

    /** The favicon link tags, or null to use the bundled set. */
    public static function faviconUrl(): ?string
    {
        return self::url(app(BrandingSettings::class)->favicon_path);
    }

    public static function appleTouchUrl(): string
    {
        return self::url(app(BrandingSettings::class)->apple_touch_path) ?? asset('apple-touch-icon.png');
    }

    /** Absolute path of the share-image logo (a raster image). */
    public static function ogLogoPath(): string
    {
        $path = self::existing(app(BrandingSettings::class)->og_logo_path);

        return $path !== null ? Storage::disk('public')->path($path) : resource_path('og/logo.png');
    }

    /** A data URI for the invoice PDF: the upload, or the bundled SVG. */
    public static function logoDataUri(): string
    {
        $path = self::existing(app(BrandingSettings::class)->logo_path);
        if ($path === null) {
            return 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(public_path('brand/logo-mark.svg')));
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return "data:{$mime};base64,".base64_encode((string) Storage::disk('public')->get($path));
    }

    private static function url(?string $path): ?string
    {
        $path = self::existing($path);

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    private static function existing(?string $path): ?string
    {
        return $path !== null && $path !== '' && Storage::disk('public')->exists($path) ? $path : null;
    }
}
