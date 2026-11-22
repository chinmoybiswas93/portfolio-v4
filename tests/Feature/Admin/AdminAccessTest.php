<?php

declare(strict_types=1);

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends guests to the login page', function (): void {
    $this->get(adminPath())->assertRedirect(adminPath('/login'));
});

it('forbids users who are not admins', function (): void {
    $this->actingAs(User::factory()->create())->get(adminPath())->assertForbidden();
});

it('makes admins set up two-factor authentication before anything else', function (): void {
    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();

    $this->actingAs($admin)->get(adminPath())->assertRedirectContains(adminPath('/multi-factor-authentication/set-up'));
});

it('lets an admin with two-factor authentication into every screen', function (string $path): void {
    $this->actingAs(adminWithMfa())->get(adminPath($path))->assertOk();
})->with([
    '',
    '/services', '/services/create',
    '/experiences', '/experiences/create',
    '/projects', '/projects/create',
    '/posts', '/posts/create',
    '/categories', '/tags', '/redirects',
    '/settings/profile', '/settings/seo', '/settings/invoicing', '/settings/payment',
    '/clients', '/invoices', '/invoices/create',
    '/profile',
]);

it('has no registration or password-reset pages', function (): void {
    $names = collect(Route::getRoutes()->getRoutesByName())->keys();

    // OAuth app registration for MCP (mcp.oauth.register) is not user sign-up; it is allowed on purpose (ADR-0009).
    expect($names->reject(fn (string $n): bool => $n === 'mcp.oauth.register')
        ->filter(fn (string $n): bool => str_contains($n, 'register') || str_contains($n, 'password-reset') || str_contains($n, 'password.reset')))->toBeEmpty();
    $this->get(adminPath('/register'))->assertNotFound();
    $this->get(adminPath('/password-reset/request'))->assertNotFound();
});

it('blocks sign-in after too many attempts in an hour', function (): void {
    for ($i = 0; $i < Login::MAX_ATTEMPTS_PER_HOUR; $i++) {
        RateLimiter::hit('admin-login:127.0.0.1', 3600);
    }

    Livewire::test(Login::class)
        ->set('data.email', 'someone@example.com')
        ->set('data.password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);
});

it('serves the panel only under the configured path, so /admin is not found', function (): void {
    expect(config('admin.path'))->toBe('panel-under-test');

    $this->get(adminPath('/login'))->assertOk();
    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
});

it('never lists the admin path in robots.txt and keeps admin pages out of search', function (): void {
    $this->get('/robots.txt')->assertDontSee('panel-under-test')->assertDontSee('/admin');
    $this->get(adminPath('/login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('sends security headers on admin pages too', function (): void {
    $response = $this->get(adminPath('/login'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
