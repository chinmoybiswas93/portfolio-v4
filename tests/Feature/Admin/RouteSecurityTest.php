<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 * Admin-only hardening (slice 3.5). The datasets come from the router, so new admin screens are covered automatically.
 */

/** Admin routes reachable without signing in. */
const PUBLIC_ADMIN_ROUTES = ['filament.admin.auth.login'];

/** Routes that exist but are switched off (export/import is not used). */
const DISABLED_ROUTES = ['filament.exports.download', 'filament.imports.failed-rows.download'];

/** Signed GET links (email change verification and block, ADR-0010): the signature is checked before 2FA set-up. */
const SIGNED_ADMIN_ROUTES = [
    'filament.admin.auth.email-change-verification.verify',
    'filament.admin.auth.email-change-verification.block-verification',
];

/** The only routes allowed to change state. */
const WRITE_ROUTES = [
    'default-livewire.update', 'livewire.upload-file', 'storage.local.upload', 'filament.admin.auth.logout',
    // MCP and OAuth (ADR-0009), added on purpose: token exchange, dynamic registration, consent approve/deny.
    'passport.token', 'mcp.oauth.register', 'passport.authorizations.approve', 'passport.authorizations.deny',
];

/** Unnamed write routes allowed by URI: the MCP endpoint (POST calls, DELETE ends a client session). */
const WRITE_URIS = ['mcp'];

/**
 * @return array<string, array{string}>
 */
function adminGetPaths(): array
{
    static $paths = null;
    if ($paths !== null) {
        return $paths;
    }

    // Datasets are built before the test app boots, so read the routes from a separately booted app.
    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $paths = collect($app->make('router')->getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r): bool => in_array('GET', $r->methods(), true)
            && (str_starts_with($r->uri(), (string) $app->make('config')->get('admin.path')) || str_starts_with($r->uri(), 'filament'))
            && ! in_array($r->getName(), [...PUBLIC_ADMIN_ROUTES, ...DISABLED_ROUTES], true))
        ->mapWithKeys(fn (RoutingRoute $r): array => [(string) $r->getName() => ['/'.preg_replace('/\{[^}]+\}/', '1', $r->uri())]])
        ->all();
}

it('finds the admin screens to check', function (): void {
    expect(count(adminGetPaths()))->toBeGreaterThan(20);
});

it('redirects guests to the login page from every admin route', function (string $path): void {
    $this->get($path)->assertRedirect(adminPath('/login'));
})->with(fn (): array => adminGetPaths());

it('forbids signed-in non-admins on every admin route', function (string $path): void {
    $this->actingAs(User::factory()->create(['is_admin' => false]))->get($path)->assertForbidden();
})->with(fn (): array => adminGetPaths());

// Covers custom panel routes too: Filament only adds the 2FA middleware to its own pages.
it('makes admins without two-factor authentication set it up first on every admin route', function (string $path): void {
    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();

    $this->actingAs($admin)->get($path)->assertRedirectContains(adminPath('/multi-factor-authentication/set-up'));
})->with(fn (): array => collect(adminGetPaths())->except(['filament.admin.auth.multi-factor-authentication.set-up-required', ...SIGNED_ADMIN_ROUTES])->all());

// Email change links (ADR-0010) check their signature first: an unsigned or tampered link is refused for everyone.
it('refuses email change links without a valid signature', function (string $path): void {
    $admin = adminWithMfa();

    $this->actingAs($admin)->get($path)->assertForbidden();
    $this->actingAs($admin)->get($path.'?expires=9999999999&signature=tampered')->assertForbidden();
})->with(fn (): array => collect(adminGetPaths())->only(SIGNED_ADMIN_ROUTES)->all());

it('answers 404 for the unused export and import downloads', function (string $path): void {
    $this->get($path)->assertNotFound();
    $this->actingAs(adminWithMfa())->get($path)->assertNotFound();
})->with(['/filament/exports/1/download', '/filament/imports/1/failed-rows/download']);

it('has no state-changing routes beyond the allowed list', function (): void {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r): bool => array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn (RoutingRoute $r): string => implode('|', $r->methods()).' '.$r->uri().' ('.($r->getName() ?? 'unnamed').')')
        ->values();

    $unexpected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r): bool => array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []
            && ! in_array($r->getName(), WRITE_ROUTES, true) && ! in_array($r->uri(), WRITE_URIS, true))
        ->map(fn (RoutingRoute $r): string => implode('|', $r->methods()).' '.$r->uri())
        ->values()->all();

    // WRITE_URIS: /mcp answers POST and DELETE.
    expect($unexpected)->toBe([])->and($writes)->toHaveCount(count(WRITE_ROUTES) + 2);
});

it('only accepts signed private-file uploads, and /storage takes no writes at all', function (): void {
    $this->put('/private-files/invoices/2026/evil.pdf', ['x' => 1])->assertForbidden();
    $this->get('/private-files/invoices/2026/INV-2026-0001-v1.pdf')->assertForbidden(); // unsigned read
    $this->put('/storage/invoices/2026/evil.pdf', ['x' => 1])->assertStatus(405);
});

it('answers the health check without a session cookie, rate limited', function (): void {
    $this->get('/up')->assertOk()->assertHeaderMissing('Set-Cookie')->assertHeader('X-RateLimit-Limit', '30');
});

it('rejects a tampered Livewire snapshot for the invoice editor', function (): void {
    $this->actingAs(adminWithMfa());
    $html = $this->get(adminPath('/invoices/create'))->assertOk()->getContent();

    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $html, $matches);
    $snapshot = collect($matches[1])
        ->map(fn (string $s): array => (array) json_decode(html_entity_decode($s, ENT_QUOTES), true))
        ->first(fn (array $s): bool => ($s['memo']['name'] ?? null) === CreateInvoice::class);
    expect($snapshot)->not->toBeNull();

    // Swap the component for another one while keeping the original checksum.
    $tampered = $snapshot;
    $tampered['memo']['name'] = EditInvoice::class;

    $response = $this->withHeaders(['X-Livewire' => '1'])->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => json_encode($tampered), 'updates' => [], 'calls' => []]],
    ]);

    $response->assertStatus(419);
    expect(Invoice::query()->count())->toBe(0);
});
