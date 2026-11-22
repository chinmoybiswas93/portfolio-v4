<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OAuth consent screen is for the owner only: signed in to the admin panel, an admin, with 2FA enabled.
 * Guests go to the private admin login and come back here afterwards.
 */
final class EnsureOwnerForConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! EnsureMcpEnabled::isOn()) {
            abort(503, 'The MCP server is switched off.');
        }

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $user = $panel->auth()->user();

        if (! $user instanceof User) {
            // Never redirect to the login: its URL is the private admin path. The owner signs in first, then retries.
            return response()->view('mcp.sign-in-first', [], 401)
                ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('X-Frame-Options', 'DENY');
        }
        abort_unless($user->is_admin, 403);
        if (! MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return redirect()->guest($panel->getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
