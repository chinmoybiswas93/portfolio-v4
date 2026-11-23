<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use SensitiveParameter;

/**
 * Account security (ADR-0010). The name is edited inline; email and password each change in their own dialog,
 * with every field visible from the start.
 *
 * - Email: current password and an authenticator code. The new address must confirm a signed link first, and the
 *   old address gets a notice with a link that blocks the change (Filament email change verification).
 * - Password: current password, an authenticator code and the new password twice (12+ characters, not leaked).
 *   Every other session is signed out (AuthenticateSession) and the account email gets a notice.
 * - Both are rate limited to 5 attempts a minute per user (wrong passwords and codes count), and need two-factor
 *   authentication to be set up.
 */
class EditProfile extends BaseEditProfile
{
    public const int MAX_ATTEMPTS = 5;

    public function form(Schema $schema): Schema
    {
        return $schema->components([$this->getNameFormComponent()]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profile')->compact()->schema([$this->getFormContentComponent()]),
            Section::make('Email')->compact()
                ->afterHeader([$this->changeEmailAction()])
                ->schema([
                    Text::make(fn (): string => $this->user()->email),
                    Text::make('Used to sign in and for security notices.')->color('gray'),
                ]),
            Section::make('Password')->compact()
                ->afterHeader([$this->changePasswordAction()])
                ->schema([Text::make('Changing it signs out every other session.')->color('gray')]),
            ...Arr::wrap($this->securityNotice()),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
        ]);
    }

    public function changeEmailAction(): Action
    {
        return Action::make('changeEmail')->label('Change email')->color('gray')->outlined()
            ->visible(fn (): bool => $this->hasTwoFactor())
            ->modalHeading('Change email')->modalWidth(Width::Medium)
            ->modalDescription('We send a link to the new address. Nothing changes until you click it; your current address gets a notice with a link to block the change.')
            ->modalSubmitActionLabel('Send verification link')
            ->schema([
                $this->currentPasswordField('email'),
                TextInput::make('new_email')->label('New email')->email()->required()->maxLength(255)->autocomplete('email')
                    ->rule(fn (): Unique => Rule::unique('users', 'email')->ignore($this->user()->getKey()))
                    ->rule(fn (): NotIn => Rule::notIn([$this->user()->email]))
                    ->validationMessages(['not_in' => 'This is already your email.']),
                $this->codeField(),
            ])
            ->action(function (array $data): void {
                $this->sendEmailChangeVerification($this->user(), (string) $data['new_email']);
            });
    }

    public function changePasswordAction(): Action
    {
        return Action::make('changePassword')->label('Change password')->color('gray')->outlined()
            ->visible(fn (): bool => $this->hasTwoFactor())
            ->modalHeading('Change password')->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Change password')
            ->schema([
                $this->currentPasswordField('password'),
                $this->codeField(),
                TextInput::make('new_password')->label('New password')->password()->revealable()->required()
                    ->rule(Password::default())->different('current_password')->autocomplete('new-password')
                    ->helperText('At least 12 characters; not a known leaked password.'),
                TextInput::make('new_password_confirmation')->label('Confirm new password')->password()->revealable()
                    ->required()->same('new_password')->autocomplete('new-password'),
            ])
            ->action(function (array $data): void {
                $user = $this->user();
                $user->password = Hash::make((string) $data['new_password']);
                $user->save();

                // Keep this session; AuthenticateSession signs out every other one on its next request.
                session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
                Auth::guard(Filament::getAuthGuard())->setUser($user);
                $user->notify(new PasswordChanged);

                Notification::make()->success()->title('Password changed')->body('Every other session is signed out.')->send();
            });
    }

    /**
     * Rate limited inside validation (5 tries a minute per user and purpose), so every submission counts, including
     * ones with a wrong password or code. It is the first field, so the limit is checked before anything else.
     */
    private function currentPasswordField(string $purpose): TextInput
    {
        return TextInput::make('current_password')->label('Current password')->password()->revealable()->required()
            ->autocomplete('current-password')
            ->rule(fn (): Closure => function (string $attribute, #[SensitiveParameter] mixed $value, Closure $fail) use ($purpose): void {
                $key = "admin:account:{$purpose}:".$this->user()->getKey();
                if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                    $fail('Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

                    return;
                }
                RateLimiter::hit($key);
                if (! Hash::check((string) $value, $this->user()->getAuthPassword())) {
                    $fail('The password is incorrect.');
                }
            });
    }

    private function codeField(): TextInput
    {
        return TextInput::make('code')->label('Authenticator code')->required()->length(6)->numeric()
            ->autocomplete('one-time-code')->extraInputAttributes(['inputmode' => 'numeric'])
            ->rule(fn (): Closure => function (string $attribute, #[SensitiveParameter] mixed $value, Closure $fail): void {
                if (! $this->appAuthentication()?->verifyCode((string) $value, shouldPreventCodeReuse: true)) {
                    $fail('The authenticator code is not valid.');
                }
            });
    }

    private function securityNotice(): ?Component
    {
        if ($this->hasTwoFactor()) {
            return null;
        }

        return Section::make()->compact()->schema([
            Text::make('Set up two-factor authentication below to change your email or password.')->color('warning'),
        ]);
    }

    private function hasTwoFactor(): bool
    {
        return (bool) $this->appAuthentication()?->isEnabled($this->user());
    }

    private function appAuthentication(): ?AppAuthentication
    {
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'] ?? null;

        return $provider instanceof AppAuthentication ? $provider : null;
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
