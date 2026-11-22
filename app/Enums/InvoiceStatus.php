<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
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
            self::Draft => [self::Sent],
            self::Sent => [self::Overdue, self::Paid, self::Cancelled],
            self::Overdue => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    /**
     * The actions this status offers. A draft has no number yet, so it is deleted rather than cancelled.
     *
     * @return list<InvoiceAction>
     */
    public function availableActions(): array
    {
        return match ($this) {
            self::Draft => [InvoiceAction::Edit, InvoiceAction::Issue, InvoiceAction::Duplicate, InvoiceAction::Delete],
            self::Sent, self::Overdue => [InvoiceAction::GeneratePdf, InvoiceAction::Download, InvoiceAction::Email, InvoiceAction::MarkPaid, InvoiceAction::Cancel, InvoiceAction::Duplicate],
            self::Paid => [InvoiceAction::GeneratePdf, InvoiceAction::Download, InvoiceAction::Email, InvoiceAction::Duplicate],
            self::Cancelled => [InvoiceAction::Download, InvoiceAction::Duplicate],
        };
    }

    public function allows(InvoiceAction $action): bool
    {
        return in_array($action, $this->availableActions(), true);
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

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Overdue => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
