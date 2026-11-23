<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSeo;
use App\Http\Middleware\SecurityHeaders;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010: Cloudflare Web Analytics beacon, cookieless, live site only, token set in Settings → SEO.

const BEACON_TOKEN = '0123456789abcdef0123456789abcdef';

// The page cache would serve a copy rendered by another test; these tests check fresh renders.
beforeEach(fn () => config(['cache.pages_enabled' => false]));

function setAnalyticsToken(?string $token): void
{
    $seo = app(SeoSettings::class);
    $seo->analytics_token = $token;
    $seo->save();
}

it('loads the beacon in production when a token is set', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    setAnalyticsToken(BEACON_TOKEN);

    $this->get('/')->assertOk()
        // The JSON is HTML-escaped inside the attribute; the browser decodes it before the beacon reads it.
        ->assertSee('<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon="{&quot;token&quot;:&quot;'.BEACON_TOKEN.'&quot;}"></script>', false)
        ->assertHeader('Content-Security-Policy', SecurityHeaders::PUBLIC_CSP)
        ->assertCookieMissing('_ga');
    expect(SecurityHeaders::PUBLIC_CSP)->toContain('script-src \'self\' https://static.cloudflareinsights.com')
        ->toContain('connect-src \'self\' https://cloudflareinsights.com');
});

it('does not load the beacon without a token, or outside production', function (string $env, ?string $token): void {
    app()->detectEnvironment(fn (): string => $env);
    setAnalyticsToken($token);

    $this->get('/')->assertOk()->assertDontSee('cloudflareinsights', false);
})->with([
    'production, no token' => ['production', null],
    'local, token set' => ['local', BEACON_TOKEN],
    'testing, token set' => ['testing', BEACON_TOKEN],
]);

it('saves a valid token from Settings → SEO and rejects anything else', function (): void {
    $this->actingAs(adminWithMfa());

    Livewire::test(ManageSeo::class)->fillForm(['analytics_token' => 'not a token'])->call('save')
        ->assertHasFormErrors(['analytics_token']);
    Livewire::test(ManageSeo::class)->fillForm(['analytics_token' => ' '.strtoupper(BEACON_TOKEN).' '])->call('save')
        ->assertHasNoFormErrors();

    expect(app(SeoSettings::class)->refresh()->analytics_token)->toBe(BEACON_TOKEN);

    Livewire::test(ManageSeo::class)->fillForm(['analytics_token' => ''])->call('save')->assertHasNoFormErrors();
    expect(app(SeoSettings::class)->refresh()->analytics_token)->toBeNull();
});
