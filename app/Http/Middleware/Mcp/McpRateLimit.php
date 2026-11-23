<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Support\Mcp\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-token limits (60/min, 1,000/day). A custom class, not "throttle:", so it stays inside RecordMcpCall and
 * throttled calls are audited (Laravel reorders its own throttle middleware by priority).
 */
final class McpRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'mcp:'.(McpToken::id($request->user()) ?? $request->ip());
        $limits = [
            [$key.':m', (int) config('mcp-server.rate.per_minute'), 60],
            [$key.':d', (int) config('mcp-server.rate.per_day'), 86400],
        ];

        foreach ($limits as [$bucket, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($bucket, $max)) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32029, 'message' => 'Too many requests. Try again later.'],
                ], 429, ['Retry-After' => (string) RateLimiter::availableIn($bucket)]);
            }
        }
        foreach ($limits as [$bucket, , $decay]) {
            RateLimiter::hit($bucket, $decay);
        }

        return $next($request);
    }
}
