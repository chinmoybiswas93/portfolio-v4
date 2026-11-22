<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Settings\McpSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Kill switch: MCP_ENABLED (hard) and Settings → MCP (everyday). Off = 503 for /mcp and new authorisations. */
final class EnsureMcpEnabled
{
    public static function isOn(): bool
    {
        return (bool) config('mcp-server.enabled') && app(McpSettings::class)->enabled;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isOn()) {
            return response()->json(['error' => 'mcp_disabled', 'error_description' => 'The MCP server is switched off.'], 503, ['Retry-After' => '3600']);
        }

        return $next($request);
    }
}
