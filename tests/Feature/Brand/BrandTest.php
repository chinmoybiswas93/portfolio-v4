<?php

declare(strict_types=1);

use App\Settings\ProfileSettings;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('falls back to config when the profile is blank', function (): void {
    config(['brand.site_name' => 'Config Name', 'brand.contact_email' => 'config@example.com']);
    $profile = app(ProfileSettings::class);
    $profile->name = '';
    $profile->email = null;
    $profile->save();

    expect(Brand::name())->toBe('Config Name')
        ->and(Brand::email())->toBe('config@example.com');
});

it('prefers the profile settings over config', function (): void {
    config(['brand.site_name' => 'Config Name']);
    $profile = app(ProfileSettings::class);
    $profile->name = 'Jane Doe';
    $profile->email = 'jane@example.com';
    $profile->save();

    expect(Brand::name())->toBe('Jane Doe')
        ->and(Brand::email())->toBe('jane@example.com');
});

it('has neutral defaults in config', function (): void {
    expect(config('brand.site_name'))->toBe('Your Name')
        ->and(config('brand.domain'))->toBe('example.com')
        ->and(config('brand.contact_email'))->toBe('hello@example.com');
});

it('leaves existing settings rows untouched when migrations run again', function (): void {
    $profile = app(ProfileSettings::class);
    $profile->name = 'Existing Owner';
    $profile->title = 'Existing title';
    $profile->save();

    Artisan::call('migrate', ['--force' => true]);

    $fresh = app(ProfileSettings::class);
    expect($fresh->name)->toBe('Existing Owner')->and($fresh->title)->toBe('Existing title');
});
