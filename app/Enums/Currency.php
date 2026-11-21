<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case GBP = 'GBP';
    case EUR = 'EUR';
    case USD = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            self::GBP => '£',
            self::EUR => '€',
            self::USD => '$',
        };
    }

    /** Number of decimal places in the minor unit (2 for all supported currencies). */
    public function minorUnits(): int
    {
        return 2;
    }
}
