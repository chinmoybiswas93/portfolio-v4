<?php

declare(strict_types=1);

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\InvoiceCalculator;
use App\Domain\Invoicing\InvoiceLine;
use App\Enums\DiscountType;

/*
 * Written before the calculator (CLAUDE.md, docs/TESTING.md §3). All amounts are integer minor units,
 * quantities are thousandths, rates are basis points. Tax is one rate on the total after discount.
 */

mutates(InvoiceCalculator::class, InvoiceLine::class);

function line(int $quantityMilli, int $unitPriceMinor, string $description = 'Work'): InvoiceLine
{
    return new InvoiceLine($description, $quantityMilli, $unitPriceMinor);
}

beforeEach(fn () => $this->calc = new InvoiceCalculator);

it('totals a single line with no discount and no tax', function (): void {
    $t = $this->calc->calculate([line(1000, 10000)]);

    expect($t->lineAmounts)->toBe([10000])
        ->and($t->subtotal)->toBe(10000)
        ->and($t->discount)->toBe(0)
        ->and($t->taxable)->toBe(10000)
        ->and($t->tax)->toBe(0)
        ->and($t->total)->toBe(10000);
});

it('rounds fractional quantities half up', function (): void {
    expect($this->calc->calculate([line(1500, 3333)])->lineAmounts)->toBe([5000]);
});

it('rounds tax half up on the total', function (): void {
    $t = $this->calc->calculate([line(1000, 1001)], taxRateBp: 2000);

    expect($t->tax)->toBe(200)->and($t->total)->toBe(1201);
});

it('applies tax once to the total, never per line', function (): void {
    $t = $this->calc->calculate([line(1000, 3), line(1000, 3), line(1000, 3)], taxRateBp: 2000);

    expect($t->subtotal)->toBe(9)->and($t->tax)->toBe(2)->and($t->total)->toBe(11);
});

it('applies a percentage discount to the subtotal', function (): void {
    $t = $this->calc->calculate([line(1000, 9999)], DiscountType::Percent, 1000);

    expect($t->discount)->toBe(1000)->and($t->taxable)->toBe(8999)->and($t->total)->toBe(8999);
});

it('applies a fixed discount to the subtotal', function (): void {
    $t = $this->calc->calculate([line(1000, 9999)], DiscountType::Fixed, 1000);

    expect($t->discount)->toBe(1000)->and($t->total)->toBe(8999);
});

it('matches the approved invoice design sample', function (): void {
    $t = $this->calc->calculate(
        [line(1000, 35000), line(6000, 4500), line(2500, 4500)],
        DiscountType::Percent,
        500,
    );

    expect($t->lineAmounts)->toBe([35000, 27000, 11250])
        ->and($t->subtotal)->toBe(73250)
        ->and($t->discount)->toBe(3663)
        ->and($t->tax)->toBe(0)
        ->and($t->total)->toBe(69587);
});

it('charges VAT on the discounted total', function (): void {
    $t = $this->calc->calculate([line(1000, 10000)], DiscountType::Percent, 1000, 2000);

    expect($t->taxable)->toBe(9000)->and($t->tax)->toBe(1800)->and($t->total)->toBe(10800);
});

it('rejects impossible discounts and rates', function (DiscountType $type, int $value, int $rate): void {
    $this->calc->calculate([line(1000, 1000)], $type, $value, $rate);
})->with([
    'fixed discount above subtotal' => [DiscountType::Fixed, 1001, 0],
    'percent above 100%' => [DiscountType::Percent, 10001, 0],
    'negative discount' => [DiscountType::Fixed, -1, 0],
    'tax rate above 100%' => [DiscountType::None, 0, 10001],
    'negative tax rate' => [DiscountType::None, 0, -1],
])->throws(InvalidInvoice::class);

it('rejects negative quantities, negative prices and empty descriptions', function (int $qty, int $price, string $description): void {
    new InvoiceLine($description, $qty, $price);
})->with([
    'negative quantity' => [-1000, 100, 'Work'],
    'negative price' => [1000, -100, 'Work'],
    'empty description' => [1000, 100, '   '],
])->throws(InvalidInvoice::class);

it('totals an invoice with no lines as zero', function (): void {
    $t = $this->calc->calculate([]);

    expect($t->subtotal)->toBe(0)->and($t->total)->toBe(0)->and($t->lineAmounts)->toBe([]);
});

it('handles large values without overflow', function (): void {
    $t = $this->calc->calculate([line(1_000_000, 9_999_999)], taxRateBp: 2000);

    expect($t->subtotal)->toBe(9_999_999_000)
        ->and($t->tax)->toBe(1_999_999_800)
        ->and($t->total)->toBe(11_999_998_800);
});

it('keeps the totals consistent for random invoices', function (): void {
    mt_srand(20261122);
    for ($i = 0; $i < 200; $i++) {
        $lines = [];
        for ($n = mt_rand(1, 6); $n > 0; $n--) {
            $lines[] = line(mt_rand(1, 20000), mt_rand(0, 500000));
        }
        $rate = [0, 500, 2000, 2100][mt_rand(0, 3)];
        $t = $this->calc->calculate($lines, DiscountType::Percent, mt_rand(0, 3000), $rate);

        expect($t->subtotal)->toBe(array_sum($t->lineAmounts))
            ->and($t->taxable)->toBe($t->subtotal - $t->discount)
            ->and($t->tax)->toBe(intdiv($t->taxable * $rate * 2 + 10000, 20000))
            ->and($t->total)->toBe($t->taxable + $t->tax);
    }
});
