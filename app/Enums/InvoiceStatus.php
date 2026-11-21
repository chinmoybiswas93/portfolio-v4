<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Overdue, self::Paid, self::Cancelled],
            self::Overdue => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Issued invoices are locked: items, amounts and snapshots can no longer change. */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
