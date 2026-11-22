<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the admin interactively', function (): void {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password (min. 12 characters)', 'correct-horse-42-battery')
        ->expectsQuestion('Confirm password', 'correct-horse-42-battery')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
    expect($user->is_admin)->toBeTrue()->and($user->getAppAuthenticationSecret())->toBeNull();
});

it('rejects weak or mismatched passwords', function (): void {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Name', 'Owner')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password (min. 12 characters)', 'short1')
        ->expectsQuestion('Confirm password', 'short1')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses to create a second admin', function (): void {
    adminWithMfa();

    $this->artisan('app:create-admin')->assertFailed();
});

it('resets two-factor authentication over the command line', function (): void {
    $admin = adminWithMfa();

    $this->artisan('app:admin-reset', ['email' => $admin->email, '--mfa' => true])
        ->expectsConfirmation('Set a new password?', 'no')
        ->assertSuccessful();

    expect($admin->refresh()->getAppAuthenticationSecret())->toBeNull();
});
