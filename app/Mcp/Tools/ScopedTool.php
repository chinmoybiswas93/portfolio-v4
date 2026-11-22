<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\User;
use App\Support\Mcp\McpCall;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Server\Tool;

/**
 * Base for portfolio tools: each declares the scope it needs. Tools without it are not listed, and
 * EnsureMcpToolScope refuses the call on the server (ADR-0009 §3).
 */
abstract class ScopedTool extends Tool
{
    abstract public static function scope(): string;

    public function shouldRegister(HttpRequest $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->tokenCan(static::scope());
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    protected function audit(string $type, int|string|null $id, ?string $label = null, ?array $changes = null): void
    {
        if (app()->bound(McpCall::class)) {
            app(McpCall::class)->target($type, $id, $label, $changes);
        }
    }
}
