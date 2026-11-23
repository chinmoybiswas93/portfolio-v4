<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Daily job: sent invoices past their due date become overdue. Paid and cancelled invoices never change.
 */
final class MarkOverdueInvoices
{
    public function __invoke(): int
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue, 'updated_at' => now()]);
    }
}
