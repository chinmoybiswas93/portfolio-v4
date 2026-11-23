<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Notifications\VerifyEmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010: email and password change in their own dialogs, with password + authenticator code, rate limited.

const OLD_PASSWORD = 'old-password-1234';
const NEW_PASSWORD = 'a-fresh-long-passphrase-42';
const LEAKED_PASSWORD = 'leaked-but-long-password';

beforeEach(function (): void {
    Notification::fake();
    // The breach list knows exactly one password: LEAKED_PASSWORD (range API answers "SUFFIX:count" lines).
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(strtoupper(substr(sha1(LEAKED_PASSWORD), 5)).':42')]);
    $this->user = adminWithMfa();
    $this->user->forceFill(['email' => 'owner@example.test', 'password' => Hash::make(OLD_PASSWORD)])->save();
    $this->actingAs($this->user);
});

function otp(User $user): string
{
    return AppAuthentication::make()->getCurrentCode($user);
}

it('shows the name form, both change buttons and the two-factor section', function (): void {
    Livewire::test(EditProfile::class)
        ->assertSee('owner@example.test')->assertSee('Changing it signs out every other session.')
        ->assertActionVisible('changeEmail')->assertActionVisible('changePassword')
        ->assertDontSee('Set up two-factor authentication below');
});

it('sends a verification link to the new address and a notice to the old one, without changing the email yet', function (): void {
    Livewire::test(EditProfile::class)
        ->mountAction('changeEmail')
        ->fillForm(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_email' => 'new@example.test'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($this->user->refresh()->email)->toBe('owner@example.test');
    Notification::assertSentTo($this->user, NoticeOfEmailChangeRequest::class);
    Notification::assertSentOnDemand(VerifyEmailChange::class, fn (VerifyEmailChange $n, array $channels, AnonymousNotifiable $to): bool => $to->routes['mail'] === 'new@example.test');
});

it('changes the email once the signed link is opened', function (): void {
    Livewire::test(EditProfile::class)
        ->mountAction('changeEmail')
        ->fillForm(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_email' => 'new@example.test'])
        ->callMountedAction();

    $url = null;
    Notification::assertSentOnDemand(VerifyEmailChange::class, function (VerifyEmailChange $n) use (&$url): bool {
        $url = $n->url;

        return true;
    });

    $this->get((string) $url)->assertRedirect();
    expect($this->user->refresh()->email)->toBe('new@example.test');
});

it('refuses an email change with a wrong password, a wrong code, the same or a taken address', function (array $fields, string $errorOn): void {
    User::factory()->create(['email' => 'taken@example.test']);
    $data = array_merge(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_email' => 'new@example.test'], $fields);

    Livewire::test(EditProfile::class)->mountAction('changeEmail')->fillForm($data)->callMountedAction()
        ->assertHasFormErrors([$errorOn]);

    Notification::assertNothingSent();
})->with([
    'wrong password' => [['current_password' => 'not-my-password'], 'current_password'],
    'wrong code' => [['code' => '000000'], 'code'],
    'same address' => [['new_email' => 'owner@example.test'], 'new_email'],
    'taken address' => [['new_email' => 'taken@example.test'], 'new_email'],
]);

it('changes the password, keeps this session, notifies the owner and signs out other sessions', function (): void {
    Livewire::test(EditProfile::class)
        ->mountAction('changePassword')
        ->fillForm(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_password' => NEW_PASSWORD, 'new_password_confirmation' => NEW_PASSWORD])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $user = $this->user->refresh();
    expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and(session('password_hash_web'))->toBe($user->password);
    Notification::assertSentTo($user, PasswordChanged::class);
});

it('refuses a weak, leaked or mistyped new password, or a wrong password or code', function (array $fields, string $errorOn): void {
    $data = array_merge(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_password' => NEW_PASSWORD, 'new_password_confirmation' => NEW_PASSWORD], $fields);

    Livewire::test(EditProfile::class)->mountAction('changePassword')->fillForm($data)->callMountedAction()
        ->assertHasFormErrors([$errorOn]);

    expect(Hash::check(OLD_PASSWORD, $this->user->refresh()->password))->toBeTrue();
    Notification::assertNothingSent();
})->with([
    'too short' => [['new_password' => 'short-pw-11', 'new_password_confirmation' => 'short-pw-11'], 'new_password'],
    'leaked' => [['new_password' => LEAKED_PASSWORD, 'new_password_confirmation' => LEAKED_PASSWORD], 'new_password'],
    'mistyped' => [['new_password_confirmation' => 'something-else-entirely'], 'new_password_confirmation'],
    'wrong password' => [['current_password' => 'not-my-password'], 'current_password'],
    'wrong code' => [['code' => '000000'], 'code'],
]);

it('allows five attempts a minute, then refuses even the right password', function (): void {
    RateLimiter::clear('admin:account:password:'.$this->user->getKey());
    $page = Livewire::test(EditProfile::class);
    foreach (range(1, EditProfile::MAX_ATTEMPTS) as $_) {
        $page->mountAction('changePassword')->fillForm(['current_password' => 'not-my-password', 'code' => '000000'])->callMountedAction();
    }

    $page->mountAction('changePassword')
        ->fillForm(['current_password' => OLD_PASSWORD, 'code' => otp($this->user), 'new_password' => NEW_PASSWORD, 'new_password_confirmation' => NEW_PASSWORD])
        ->callMountedAction()
        ->assertHasFormErrors(['current_password']);

    expect(Hash::check(OLD_PASSWORD, $this->user->refresh()->password))->toBeTrue();
});

it('hides both changes until two-factor authentication is set up', function (): void {
    config(['admin.mfa_required' => false]);
    $this->user->saveAppAuthenticationSecret(null);
    $this->actingAs($this->user->refresh());

    Livewire::test(EditProfile::class)
        ->assertActionHidden('changeEmail')->assertActionHidden('changePassword')
        ->assertSee('Set up two-factor authentication below to change your email or password.');
});

it('signs out another session that still carries the old password', function (): void {
    $oldHash = $this->user->password;
    $this->user->forceFill(['password' => Hash::make(NEW_PASSWORD)])->save();

    // Another browser: its session was started with the old password hash.
    $this->withSession(['password_hash_web' => $oldHash])->get(adminPath())->assertRedirect(adminPath('/login'));
    $this->assertGuest();
});
