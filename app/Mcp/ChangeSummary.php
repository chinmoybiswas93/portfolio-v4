<?php

declare(strict_types=1);

namespace App\Mcp;

use Illuminate\Support\Str;

/**
 * Before/after summary for the audit log: field names with short previews, never full documents or secrets.
 */
final class ChangeSummary
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: string|null, after: string|null}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;
            if (self::text($old) !== self::text($value)) {
                $changes[$field] = ['before' => self::preview($old), 'after' => self::preview($value)];
            }
        }

        return $changes;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            is_array($value) => (string) json_encode($value),
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    private static function preview(mixed $value): ?string
    {
        return $value === null ? null : Str::limit(self::text($value), 80);
    }
}
