<?php

declare(strict_types=1);

return [
    /*
     * URL path of the Filament admin panel (ADR-0007). Production may use an unguessable value.
     */
    'path' => env('ADMIN_PATH', 'admin'),

    /*
     * Two-factor for the admin. Only honoured when APP_ENV=local (App\Support\Auth\AdminMfa): every other
     * environment requires it whatever this says.
     */
    'mfa_required' => env('ADMIN_MFA_REQUIRED', true),
];
