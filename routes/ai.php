<?php

declare(strict_types=1);

use App\Http\Middleware\Mcp\EnsureMcpEnabled;
use App\Http\Middleware\Mcp\EnsureMcpToolScope;
use App\Http\Middleware\Mcp\EnsureRegistrationOpen;
use App\Http\Middleware\Mcp\McpRateLimit;
use App\Http\Middleware\Mcp\RecordMcpCall;
use App\Mcp\PortfolioServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * MCP server (ADR-0009). Loaded by laravel/mcp with no middleware group: no session, cookies or CSRF.
 */

// OAuth discovery (RFC 9728 / RFC 8414) and dynamic client registration (RFC 7591).
Route::middleware(['throttle:mcp-oauth', EnsureRegistrationOpen::class])->group(fn () => Mcp::oauthRoutes());

// Order matters: switch → bearer token → audit (wraps the rest) → rate limit → scope check → server.
Mcp::web('/mcp', PortfolioServer::class)->middleware([
    EnsureMcpEnabled::class,
    'auth:api',
    RecordMcpCall::class,
    McpRateLimit::class,
    EnsureMcpToolScope::class,
]);
