<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Runs the check as if the app were in $env, then restores the testing environment. */
function mfaRequiredIn(string $env, mixed $flag): bool
{
    $original = app()->environment();
    app()->detectEnvironment(fn (): string => $env);
    config(['admin.mfa_required' => $flag]);

    try {
        return AdminMfa::required();
    } finally {
        app()->detectEnvironment(fn (): string => $original);
    }
}

it('always requires 2FA outside local, whatever ADMIN_MFA_REQUIRED says', function (string $env, mixed $flag): void {
    expect(mfaRequiredIn($env, $flag))->toBeTrue();
})->with(['production', 'staging', 'testing'])->with([false, 'false', '0', true]);

it('makes 2FA optional only in local with ADMIN_MFA_REQUIRED=false', function (): void {
    expect(mfaRequiredIn('local', false))->toBeFalse()
        ->and(mfaRequiredIn('local', 'false'))->toBeFalse();
});

it('still requires 2FA in local without the flag, or with any other value', function (mixed $flag): void {
    expect(mfaRequiredIn('local', $flag))->toBeTrue();
})->with([true, 'true', null, 'yes-please', '']);

it('sends an admin without 2FA to set-up in testing even with the flag off', function (): void {
    config(['admin.mfa_required' => false]);
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get(adminPath())->assertRedirectContains(adminPath('/multi-factor-authentication/set-up'));
});
