<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

/**
 * Invoice tax wording (owner decision 2026-11-22, docs/ARCHITECTURE.md §3).
 */
final class TaxRules
{
    public const REVERSE_CHARGE_NOTE = 'Reverse charge: the customer is liable to account for VAT.';

    /** EU member states (ISO 3166-1 alpha-2). Greece also uses "EL" in VAT numbers. */
    private const EU = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV',
        'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    public static function isEu(?string $countryCode): bool
    {
        return in_array(strtoupper((string) $countryCode), self::EU, true);
    }

    public static function isUk(?string $countryCode): bool
    {
        return in_array(strtoupper((string) $countryCode), ['GB', 'UK'], true);
    }

    /** Label for the client's tax ID, by country. */
    public static function clientTaxIdLabel(?string $countryCode): string
    {
        return match (true) {
            self::isUk($countryCode) => 'VAT reg. no.',
            self::isEu($countryCode) => 'VAT ID',
            strtoupper((string) $countryCode) === 'US' => 'EIN / Tax ID',
            default => 'Tax ID',
        };
    }

    /** No VAT charged to a UK/EU business that gave a tax ID: the customer accounts for VAT. */
    public static function isReverseCharge(?string $countryCode, ?string $clientTaxId, int $taxRateBp): bool
    {
        return $taxRateBp === 0
            && trim((string) $clientTaxId) !== ''
            && (self::isUk($countryCode) || self::isEu($countryCode));
    }

    /** The tax note printed on the invoice. */
    public static function note(?string $countryCode, ?string $clientTaxId, int $taxRateBp, ?string $defaultNote): ?string
    {
        if (self::isReverseCharge($countryCode, $clientTaxId, $taxRateBp)) {
            return self::REVERSE_CHARGE_NOTE;
        }

        return $taxRateBp === 0 ? (trim((string) $defaultNote) ?: null) : null;
    }
}
