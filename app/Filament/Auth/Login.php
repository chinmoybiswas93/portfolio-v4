<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Adds an hourly cap per IP on top of Filament's 5-per-minute limit (ADR-0007), and logs failures.
 */
final class Login extends BaseLogin
{
    public const MAX_ATTEMPTS_PER_HOUR = 10;

    public function authenticate(): ?LoginResponse
    {
        $key = 'admin-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_HOUR)) {
            Log::channel('security')->warning('Admin login blocked by hourly limit', ['ip' => request()->ip()]);

            throw ValidationException::withMessages([
                'data.email' => __('Too many sign-in attempts. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 3600);

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            Log::channel('security')->notice('Failed admin login', ['ip' => request()->ip()]);

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }
}
