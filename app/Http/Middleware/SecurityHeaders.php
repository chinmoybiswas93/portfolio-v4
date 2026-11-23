<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Seo\Seo;
use App\Support\Vite\DevServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers. The strict Content-Security-Policy applies to the public site only: it has no inline
 * scripts or styles. The admin panel (Filament/Livewire) gets the other headers without the CSP.
 */
final class SecurityHeaders
{
    public const PUBLIC_CSP = "default-src 'self'; script-src 'self' https://static.cloudflareinsights.com; "
        ."style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self' https://cloudflareinsights.com; "
        ."frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

    /** The public CSP. Locally, with the Vite dev server running, its origin and websocket are also allowed. */
    public static function publicCsp(): string
    {
        $origin = DevServer::origin();
        if ($origin === null) {
            return self::PUBLIC_CSP;
        }

        $socket = preg_replace('~^http~', 'ws', $origin);

        return str_replace(
            ["script-src 'self'", "style-src 'self'", "connect-src 'self'"],
            ["script-src 'self' {$origin}", "style-src 'self' {$origin}", "connect-src 'self' {$origin} {$socket}"],
            self::PUBLIC_CSP,
        );
    }

    public function handle(Request $request, Closure $next, string $profile = 'default'): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // DENY unless the response opted into same-origin framing (the admin PDF preview iframe).
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }

        // HSTS for this host only: never includeSubDomains, other sites live on subdomains of this domain.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($profile !== 'public') {
            // Admin, Livewire and /up must never be stored by the LiteSpeed server cache (ADR-0008).
            $headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
            if (! $headers->has('X-Robots-Tag')) {
                $headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
        }

        if ($profile === 'public') {
            $headers->set('Content-Security-Policy', self::publicCsp());
            $headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
            if (! Seo::indexable()) {
                $headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
        }

        return $response;
    }
}
