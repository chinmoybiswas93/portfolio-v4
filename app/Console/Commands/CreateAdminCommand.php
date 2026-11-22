<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the single admin user interactively, so the password never appears in shell history or seeders.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the admin user (only when no admin exists yet)';

    public function handle(): int
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->error('An admin already exists. Use app:admin-reset to recover access.');

            return self::FAILURE;
        }

        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: ['email' => 'email:rfc|max:255|unique:users,email']);
        $secret = password('Password (min. 12 characters)', required: true);
        $confirm = password('Confirm password', required: true);

        $validator = Validator::make(
            ['password' => $secret, 'password_confirmation' => $confirm],
            ['password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]],
        );
        if ($validator->fails()) {
            $this->error((string) $validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $secret]);
        $user->is_admin = true;
        $user->email_verified_at = now();
        $user->save();

        $this->info('Admin created. Sign in and set up two-factor authentication on first login.');

        return self::SUCCESS;
    }
}
