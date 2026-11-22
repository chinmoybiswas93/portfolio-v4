<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

/**
 * New draft with the same client, currency, lines, discount, tax rate and notes; dates and number are fresh.
 */
final class DuplicateInvoice
{
    public function __construct(private readonly RecalculateInvoice $recalculate) {}

    public function __invoke(Invoice $source): Invoice
    {
        return DB::transaction(function () use ($source): Invoice {
            $copy = new Invoice([
                'client_id' => $source->client_id,
                'currency' => $source->currency,
                'reference' => $source->reference,
                'discount_type' => $source->discount_type,
                'discount_value' => $source->discount_value,
                'tax_rate_bp' => $source->tax_rate_bp,
                'notes' => $source->notes,
            ]);
            $copy->status = InvoiceStatus::Draft;
            $copy->save();

            $source->items->each(fn (InvoiceItem $item) => $copy->items()->create($item->only(['position', 'description', 'quantity_milli', 'unit_price_minor'])));

            return ($this->recalculate)($copy);
        });
    }
}
