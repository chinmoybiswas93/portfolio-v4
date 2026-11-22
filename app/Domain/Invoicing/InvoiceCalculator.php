<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

use App\Enums\DiscountType;

/**
 * Pure invoice maths (docs/ARCHITECTURE.md §3). Integers only, half-up rounding:
 * line amount = qty × price; subtotal = Σ amounts; one invoice-level discount; one VAT rate on the total.
 */
final class InvoiceCalculator
{
    public const BASIS_POINTS = 10_000;

    /**
     * @param  list<InvoiceLine>  $lines
     */
    public function calculate(
        array $lines,
        DiscountType $discountType = DiscountType::None,
        int $discountValue = 0,
        int $taxRateBp = 0,
    ): InvoiceTotals {
        if ($taxRateBp < 0 || $taxRateBp > self::BASIS_POINTS) {
            throw new InvalidInvoice('The tax rate must be between 0% and 100%.');
        }
        if ($discountValue < 0) {
            throw new InvalidInvoice('The discount cannot be negative.');
        }

        $amounts = array_map(
            fn (InvoiceLine $line): int => self::roundHalfUp($line->quantityMilli * $line->unitPriceMinor, 1000),
            $lines,
        );
        $subtotal = array_sum($amounts);

        $discount = match ($discountType) {
            DiscountType::None => 0,
            DiscountType::Fixed => $discountValue,
            DiscountType::Percent => $discountValue > self::BASIS_POINTS
                ? throw new InvalidInvoice('A percentage discount cannot exceed 100%.')
                : self::roundHalfUp($subtotal * $discountValue, self::BASIS_POINTS),
        };
        if ($discount > $subtotal) {
            throw new InvalidInvoice('The discount cannot be larger than the subtotal.');
        }

        $taxable = $subtotal - $discount;
        $tax = self::roundHalfUp($taxable * $taxRateBp, self::BASIS_POINTS);

        return new InvoiceTotals($amounts, $subtotal, $discount, $taxable, $tax, $taxable + $tax);
    }

    /** Half-up division for non-negative integers: floor(n / d + 0.5) without floats. */
    public static function roundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
