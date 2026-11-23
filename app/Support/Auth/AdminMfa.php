<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Admin two-factor policy. Required everywhere, always; the only exception is local development on the owner's
 * machine, and only when the environment is "local" AND ADMIN_MFA_REQUIRED=false. Production, staging, testing
 * and CI ignore the flag.
 */
final class AdminMfa
{
    public static function required(): bool
    {
        if (! app()->environment('local')) {
            return true;
        }

        // Only an explicit "off" counts; anything missing, empty or unexpected keeps 2FA on.
        $flag = config('admin.mfa_required', true);
        $off = $flag === false || (is_string($flag) && in_array(strtolower(trim($flag)), ['false', '0'], true));

        return ! $off;
    }
}
