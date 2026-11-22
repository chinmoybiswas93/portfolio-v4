<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Mcp\PortfolioServer;
use App\Models\User;
use App\Support\Mcp\McpCall;
use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side scope check on every call (not only by hiding tools): the token must carry mcp:use, and a
 * tools/call needs the tool's scope. Missing scope = 403 insufficient_scope (RFC 6750, MCP step-up).
 */
final class EnsureMcpToolScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->tokenCan(Registrar::OAUTH_SCOPE)) {
            return $this->deny(Registrar::OAUTH_SCOPE, $this->requestId($request));
        }

        $call = app()->bound(McpCall::class) ? app(McpCall::class) : McpCall::fromRequest($request);
        if ($call->method === 'tools/call' && $call->tool !== null) {
            $scope = PortfolioServer::scopeFor($call->tool);
            $call->scope = $scope;
            if ($scope !== null && ! $user->tokenCan($scope)) {
                return $this->deny($scope, $this->requestId($request));
            }
        }

        return $next($request);
    }

    private function requestId(Request $request): int|string|null
    {
        $payload = json_decode((string) $request->getContent(), true);

        return is_array($payload) && (is_int($payload['id'] ?? null) || is_string($payload['id'] ?? null)) ? $payload['id'] : null;
    }

    private function deny(string $scope, int|string|null $id): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => -32003, 'message' => "This app was not given the {$scope} permission."],
        ], 403, ['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="'.$scope.'"']);
    }
}
