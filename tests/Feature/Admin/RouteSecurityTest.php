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

/** The only routes allowed to change state. */
const WRITE_ROUTES = ['default-livewire.update', 'livewire.upload-file', 'storage.local.upload', 'filament.admin.auth.logout'];

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
})->with(fn (): array => collect(adminGetPaths())->except(['filament.admin.auth.multi-factor-authentication.set-up-required'])->all());

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
            && ! in_array($r->getName(), WRITE_ROUTES, true))
        ->map(fn (RoutingRoute $r): string => implode('|', $r->methods()).' '.$r->uri())
        ->values()->all();

    expect($unexpected)->toBe([])->and($writes)->toHaveCount(count(WRITE_ROUTES));
});

it('only accepts signed storage uploads', function (): void {
    $this->put('/storage/invoices/2026/evil.pdf', ['x' => 1])->assertForbidden();
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
