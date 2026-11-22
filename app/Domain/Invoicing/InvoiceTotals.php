<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

final readonly class InvoiceTotals
{
    /**
     * @param  list<int>  $lineAmounts
     */
    public function __construct(
        public array $lineAmounts,
        public int $subtotal,
        public int $discount,
        public int $taxable,
        public int $tax,
        public int $total,
    ) {}
}
