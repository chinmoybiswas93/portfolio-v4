<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

/**
 * One invoice line: quantity in thousandths (1500 = 1.5), unit price in minor units. No per-line tax or discount.
 */
final readonly class InvoiceLine
{
    public function __construct(
        public string $description,
        public int $quantityMilli,
        public int $unitPriceMinor,
    ) {
        if (trim($description) === '') {
            throw new InvalidInvoice('Every line needs a description.');
        }
        if ($quantityMilli < 0 || $unitPriceMinor < 0) {
            throw new InvalidInvoice('Quantities and prices cannot be negative.');
        }
    }
}
