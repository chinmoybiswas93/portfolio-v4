<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Settings\McpSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** New apps can register only while MCP is on and registration is open (Settings → MCP). */
final class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('oauth/register')
            && (! EnsureMcpEnabled::isOn() || ! app(McpSettings::class)->registration_open)) {
            return response()->json(['error' => 'access_denied', 'error_description' => 'New app registration is closed.'], 403);
        }

        return $next($request);
    }
}
