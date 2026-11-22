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
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('forbids users who are not admins', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('makes admins set up two-factor authentication before anything else', function (): void {
    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();

    $this->actingAs($admin)->get('/admin')->assertRedirectContains('/admin/multi-factor-authentication/set-up');
});

it('lets an admin with two-factor authentication into every screen', function (string $path): void {
    $this->actingAs(adminWithMfa())->get($path)->assertOk();
})->with([
    '/admin',
    '/admin/services', '/admin/services/create',
    '/admin/experiences', '/admin/experiences/create',
    '/admin/projects', '/admin/projects/create',
    '/admin/posts', '/admin/posts/create',
    '/admin/categories', '/admin/tags', '/admin/redirects',
    '/admin/settings/profile', '/admin/settings/seo', '/admin/settings/invoicing', '/admin/settings/payment',
    '/admin/clients', '/admin/invoices', '/admin/invoices/create',
    '/admin/profile',
]);

it('has no registration or password-reset pages', function (): void {
    $names = collect(Route::getRoutes()->getRoutesByName())->keys();

    expect($names->filter(fn (string $n): bool => str_contains($n, 'register') || str_contains($n, 'password-reset') || str_contains($n, 'password.reset')))->toBeEmpty();
    $this->get('/admin/register')->assertNotFound();
    $this->get('/admin/password-reset/request')->assertNotFound();
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

it('sends the admin path from configuration', function (): void {
    expect(config('admin.path'))->toBe('admin');
});

it('sends security headers on admin pages too', function (): void {
    $response = $this->get('/admin/login');

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
