<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Illuminate\Http\Request;

/**
 * The parsed JSON-RPC call of the current /mcp request, plus what the tool reports for the audit log.
 */
final class McpCall
{
    public string $method = '';

    public ?string $tool = null;

    /** @var array<string, mixed> */
    public array $arguments = [];

    public ?string $scope = null;

    public ?string $targetType = null;

    public ?string $targetId = null;

    public ?string $targetLabel = null;

    /** @var array<string, mixed>|null */
    public ?array $changes = null;

    public static function fromRequest(Request $request): self
    {
        $call = new self;
        $payload = json_decode((string) $request->getContent(), true);
        if (is_array($payload)) {
            $call->method = is_string($payload['method'] ?? null) ? $payload['method'] : '';
            if ($call->method === 'tools/call') {
                $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
                $call->tool = is_string($params['name'] ?? null) ? $params['name'] : null;
                $call->arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
            }
        }

        return $call;
    }

    /**
     * Called by tools to describe what they touched.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function target(string $type, int|string|null $id, ?string $label = null, ?array $changes = null): void
    {
        $this->targetType = $type;
        $this->targetId = $id === null ? null : (string) $id;
        $this->targetLabel = $label === null ? null : mb_substr($label, 0, 255);
        $this->changes = $changes;
    }
}
