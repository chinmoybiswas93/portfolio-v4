<?php

declare(strict_types=1);

namespace App\Support\Vite;

use Illuminate\Support\Facades\Vite;

/**
 * The running Vite dev server (`composer dev`), local development only. In every other environment this is
 * null, so production headers and caching never change.
 */
final class DevServer
{
    /** The server's origin, e.g. "https://app.test:5173", or null when it is not running or not local. */
    public static function origin(): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        $hotFile = Vite::hotFile();
        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        // Accept a plain origin only; anything else is ignored rather than put into a header.
        return preg_match('~^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$~', $url) === 1 ? $url : null;
    }
}
