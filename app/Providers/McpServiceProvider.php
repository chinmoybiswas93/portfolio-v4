<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Mcp\ApprovedScopeRepository;
use App\Support\Mcp\McpToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Passport;

/**
 * OAuth 2.1 (Passport) and MCP wiring (ADR-0009).
 */
final class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passport's own routes are replaced by routes/oauth.php (consent behind the admin login + 2FA).
        Passport::ignoreRoutes();
        $this->app->bind(ScopeRepository::class, ApprovedScopeRepository::class);
    }

    public function boot(): void
    {
        Passport::tokensCan([Registrar::OAUTH_SCOPE => 'Use the MCP server', ...config('mcp-server.scopes')]);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::authorizationView('mcp.consent');

        RateLimiter::for('mcp', function (Request $request): array {
            $key = 'mcp:'.(McpToken::id($request->user()) ?? $request->ip());

            return [
                Limit::perMinute((int) config('mcp-server.rate.per_minute'))->by($key.':m'),
                Limit::perDay((int) config('mcp-server.rate.per_day'))->by($key.':d'),
            ];
        });

        RateLimiter::for('mcp-oauth', fn (Request $request): Limit => $request->isMethod('POST') && $request->is('oauth/register')
            ? Limit::perHour(10)->by('mcp-register:'.$request->ip())
            : Limit::perMinute(60)->by('mcp-oauth:'.$request->ip()));
    }
}
