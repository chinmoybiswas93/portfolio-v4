<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One MCP call. Append-only: updates and deletes are refused (pruning after the retention period uses a mass
 * delete query, which bypasses model events).
 */
class McpAuditLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime', 'http_status' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('MCP audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('MCP audit entries cannot be deleted.'));
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('mcp-server.audit_retention_days')));
    }
}
