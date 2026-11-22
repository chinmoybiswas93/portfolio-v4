<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Models\McpAuditLog;
use App\Models\User;
use App\Support\Mcp\McpCall;
use App\Support\Mcp\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Audits every tools/call: ok, denied (403), throttled (429) or error. Never stores the token or headers.
 */
final class RecordMcpCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $call = McpCall::fromRequest($request);
        app()->instance(McpCall::class, $call);

        $response = $next($request);

        if ($call->method === 'tools/call') {
            try {
                $this->record($request, $call, $response);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    private function record(Request $request, McpCall $call, Response $response): void
    {
        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getContent(), true);
        $isError = is_array($body) && (isset($body['error']) || (($body['result']['isError'] ?? false) === true));
        $result = match (true) {
            $status === 403 => 'denied',
            $status === 429 => 'throttled',
            $status >= 400 || $isError => 'error',
            default => 'ok',
        };
        $error = null;
        if ($result !== 'ok' && is_array($body)) {
            $error = $body['error']['message'] ?? $body['error_description'] ?? ($body['result']['content'][0]['text'] ?? null);
        }

        $user = $request->user();

        McpAuditLog::query()->create([
            'client_id' => McpToken::clientId($user),
            'client_name' => McpToken::clientName($user),
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'token_id' => McpToken::id($user),
            'method' => $call->method,
            'tool' => $call->tool,
            'scope' => $call->scope,
            'target_type' => $call->targetType,
            'target_id' => $call->targetId,
            'target_label' => $call->targetLabel,
            'changes' => $call->changes,
            'result' => $result,
            'http_status' => $status,
            'error' => is_string($error) ? Str::limit($error, 480) : null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }
}
