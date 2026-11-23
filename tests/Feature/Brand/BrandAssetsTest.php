<?php

declare(strict_types=1);

use App\Settings\BrandingSettings;
use App\Support\BrandAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uses the bundled files when nothing is uploaded', function (): void {
    expect(BrandAssets::logoUrl())->toEndWith('brand/logo-mark.svg')
        ->and(BrandAssets::faviconUrl())->toBeNull()
        ->and(BrandAssets::appleTouchUrl())->toEndWith('apple-touch-icon.png')
        ->and(BrandAssets::ogLogoPath())->toBe(resource_path('og/logo.png'))
        ->and(BrandAssets::logoDataUri())->toStartWith('data:image/svg+xml;base64,');
});

it('uses the uploads from Settings when they exist', function (): void {
    Storage::disk('public')->putFileAs('brand', UploadedFile::fake()->image('l.png', 40, 40), 'l.png');
    $settings = app(BrandingSettings::class);
    $settings->logo_path = 'brand/l.png';
    $settings->favicon_path = 'brand/l.png';
    $settings->og_logo_path = 'brand/l.png';
    $settings->save();

    expect(BrandAssets::logoUrl())->toContain('brand/l.png')
        ->and(BrandAssets::faviconUrl())->toContain('brand/l.png')
        ->and(BrandAssets::ogLogoPath())->toEndWith('brand/l.png')
        ->and(BrandAssets::logoDataUri())->toStartWith('data:image/png;base64,');
});

it('falls back when an uploaded file is missing', function (): void {
    $settings = app(BrandingSettings::class);
    $settings->logo_path = 'brand/gone.png';
    $settings->save();

    expect(BrandAssets::logoUrl())->toEndWith('brand/logo-mark.svg');
});

it('renders the branding settings page for the admin', function (): void {
    $this->actingAs(adminWithMfa())->get(adminPath('/settings/branding'))->assertOk()->assertSee('Logo mark');
});
