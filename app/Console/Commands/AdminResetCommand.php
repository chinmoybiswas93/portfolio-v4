<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

/**
 * Recovery path for the single admin (ADR-0007): reset the password and/or two-factor authentication.
 * Run over SSH only; there is no password-reset page.
 */
final class AdminResetCommand extends Command
{
    protected $signature = 'app:admin-reset {email : The admin email address} {--mfa : Also remove two-factor authentication so it is set up again on next login}';

    protected $description = 'Reset the admin password and optionally two-factor authentication';

    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->where('is_admin', true)->first();
        if ($user === null) {
            $this->error('No admin with that email.');

            return self::FAILURE;
        }

        if (confirm('Set a new password?', default: true)) {
            $secret = password('New password (min. 12 characters)', required: true);
            $confirmation = password('Confirm password', required: true);
            $validator = Validator::make(
                ['password' => $secret, 'password_confirmation' => $confirmation],
                ['password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]],
            );
            if ($validator->fails()) {
                $this->error((string) $validator->errors()->first('password'));

                return self::FAILURE;
            }
            $user->password = $secret;
        }

        if ($this->option('mfa')) {
            $user->saveAppAuthenticationSecret(null);
            $user->saveAppAuthenticationRecoveryCodes(null);
        }

        $user->setRememberToken(null);
        $user->save();
        $this->info('Admin updated. Existing "remember me" sessions were signed out.');

        return self::SUCCESS;
    }
}
