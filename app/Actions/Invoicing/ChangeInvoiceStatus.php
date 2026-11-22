<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Status changes after issue (paid, overdue, cancelled), guarded by InvoiceStatus::canTransitionTo().
 */
final class ChangeInvoiceStatus
{
    public function __invoke(Invoice $invoice, InvoiceStatus $to): Invoice
    {
        if (! $invoice->status->canTransitionTo($to)) {
            throw new InvalidInvoice("An invoice that is {$invoice->status->getLabel()} cannot become {$to->getLabel()}.");
        }

        $invoice->status = $to;
        match ($to) {
            InvoiceStatus::Paid => $invoice->paid_at = now(),
            InvoiceStatus::Cancelled => $invoice->cancelled_at = now(),
            default => null,
        };
        $invoice->save();

        return $invoice;
    }
}
