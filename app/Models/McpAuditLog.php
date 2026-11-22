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

    /** "Post #12 · Speeding up WordPress" */
    public function targetText(): string
    {
        $type = $this->target_type === null ? '' : ucfirst((string) $this->target_type).(is_numeric($this->target_id) ? ' #'.$this->target_id : '');

        return trim($type.($type !== '' && $this->target_label !== null ? ' · ' : '').($this->target_label ?? '')) ?: '—';
    }

    /** One-line summary of the change, e.g. "title, body_md" or "moved to trash"; errors for refused calls. */
    public function changeText(): ?string
    {
        if ($this->result !== 'ok') {
            return $this->error;
        }
        $changes = $this->getAttribute('changes');
        $changes = is_array($changes) ? $changes : [];
        $action = isset($changes['action']) && is_string($changes['action']) ? $changes['action'] : null;
        $fields = isset($changes['fields']) && is_array($changes['fields']) ? array_keys($changes['fields']) : array_keys(array_diff_key($changes, array_flip(['action', 'slot', 'media_id', 'total'])));
        $parts = array_filter([$action === 'trashed' ? 'moved to trash' : $action, $fields === [] ? null : implode(', ', $fields)]);

        return $parts === [] ? null : implode(': ', $parts);
    }
}
