<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Seo\Seo;
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

    public function handle(Request $request, Closure $next, string $profile = 'default'): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('X-Frame-Options', 'DENY');

        // HSTS for this host only: never includeSubDomains, other sites live on subdomains of this domain.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($profile !== 'public') {
            // Admin, Livewire and /up must never be stored by the LiteSpeed server cache (ADR-0008).
            $headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        }

        if ($profile === 'public') {
            $headers->set('Content-Security-Policy', self::PUBLIC_CSP);
            $headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
            if (! Seo::indexable()) {
                $headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
        }

        return $response;
    }
}
