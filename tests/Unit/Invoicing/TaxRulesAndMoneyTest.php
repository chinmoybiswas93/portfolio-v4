<?php

declare(strict_types=1);

use App\Domain\Invoicing\TaxRules;
use App\Enums\Currency;
use App\Support\Money;

it('labels the client tax ID by country', function (?string $country, string $label): void {
    expect(TaxRules::clientTaxIdLabel($country))->toBe($label);
})->with([
    ['GB', 'VAT reg. no.'], ['DE', 'VAT ID'], ['IE', 'VAT ID'], ['US', 'EIN / Tax ID'], ['BD', 'Tax ID'], [null, 'Tax ID'],
]);

it('applies reverse charge only to UK/EU clients with a tax ID at 0%', function (?string $country, ?string $taxId, int $rate, bool $expected): void {
    expect(TaxRules::isReverseCharge($country, $taxId, $rate))->toBe($expected);
})->with([
    'UK business' => ['GB', 'GB123456789', 0, true],
    'EU business' => ['DE', 'DE123456789', 0, true],
    'EU without tax ID' => ['DE', null, 0, false],
    'US business' => ['US', '12-3456789', 0, false],
    'VAT charged' => ['GB', 'GB123456789', 2000, false],
]);

it('chooses the invoice tax note', function (): void {
    expect(TaxRules::note('FR', 'FR12345678901', 0, 'VAT not applicable'))->toBe(TaxRules::REVERSE_CHARGE_NOTE)
        ->and(TaxRules::note('US', null, 0, 'VAT not applicable'))->toBe('VAT not applicable')
        ->and(TaxRules::note('GB', null, 2000, 'VAT not applicable'))->toBeNull()
        ->and(TaxRules::note('US', null, 0, '  '))->toBeNull();
});

it('formats money without floats', function (int $minor, Currency $currency, string $text): void {
    expect(Money::format($minor, $currency))->toBe($text);
})->with([
    [69587, Currency::GBP, '£695.87'], [123456789, Currency::EUR, '€1,234,567.89'], [5, Currency::USD, '$0.05'], [-3663, Currency::GBP, '−£36.63'], [0, Currency::GBP, '£0.00'],
]);

it('parses decimal input safely', function (mixed $input, int $scale, ?int $expected): void {
    expect(Money::parseDecimal($input, $scale))->toBe($expected);
})->with([
    ['12.5', 2, 1250], ['1,200.00', 2, 120000], ['2.5', 3, 2500], ['0', 2, 0], [7, 2, 700],
    ['12.345', 2, null], ['-1', 2, null], ['abc', 2, null], ['1e3', 2, null], ['', 2, null],
]);

it('renders scaled integers as decimal strings', function (): void {
    expect(Money::toDecimalString(1250, 2))->toBe('12.50')
        ->and(Money::toDecimalString(1500, 3, trim: true))->toBe('1.5')
        ->and(Money::toDecimalString(6000, 3, trim: true))->toBe('6');
});

it('formats Bangladeshi taka with the symbol on screen and the code in PDFs', function (): void {
    expect(Money::format(123450, Currency::BDT))->toBe('৳1,234.50')
        ->and(Money::format(123450, Currency::BDT, forPdf: true))->toBe('BDT 1,234.50')
        ->and(Money::format(-3663, Currency::BDT, forPdf: true))->toBe('−BDT 36.63')
        ->and(Money::format(69587, Currency::GBP, forPdf: true))->toBe('£695.87')
        ->and(Currency::BDT->minorUnits())->toBe(2);
});
