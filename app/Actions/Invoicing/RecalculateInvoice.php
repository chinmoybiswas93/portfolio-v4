<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\InvoiceCalculator;
use App\Domain\Invoicing\InvoiceLine;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

/**
 * Writes line amounts and invoice totals from the items (drafts only: issued invoices are locked).
 */
final class RecalculateInvoice
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function __invoke(Invoice $invoice): Invoice
    {
        if ($invoice->status->isLocked()) {
            throw new InvalidInvoice('Issued invoices cannot be changed.');
        }

        $items = $invoice->items()->get();
        $totals = $this->calculator->calculate(
            $items->map(fn (InvoiceItem $i): InvoiceLine => new InvoiceLine($i->description, $i->quantity_milli, $i->unit_price_minor))->all(),
            $invoice->discount_type,
            $invoice->discount_value,
            $invoice->tax_rate_bp,
        );

        DB::transaction(function () use ($invoice, $items, $totals): void {
            foreach ($items->values() as $index => $item) {
                $item->forceFill(['amount_minor' => $totals->lineAmounts[$index], 'position' => $index])->save();
            }
            $invoice->forceFill([
                'subtotal_minor' => $totals->subtotal,
                'discount_minor' => $totals->discount,
                'tax_minor' => $totals->tax,
                'total_minor' => $totals->total,
            ])->save();
        });

        return $invoice->refresh();
    }
}
