<?php

declare(strict_types=1);

return [
    /*
     * URL path of the Filament admin panel (ADR-0007). Production may use an unguessable value.
     */
    'path' => env('ADMIN_PATH', 'admin'),
];
