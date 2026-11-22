<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case GBP = 'GBP';
    case EUR = 'EUR';
    case USD = 'USD';
    case BDT = 'BDT';

    public function symbol(): string
    {
        return match ($this) {
            self::GBP => '£',
            self::EUR => '€',
            self::USD => '$',
            self::BDT => '৳',
        };
    }

    /**
     * Prefix used in PDFs. The bundled PDF fonts (Inter, DejaVu Sans) have no ৳ glyph, so the taka is written
     * as its code there ("BDT 1,234.50"); the admin and website use the symbol.
     */
    public function pdfPrefix(): string
    {
        return $this === self::BDT ? 'BDT ' : $this->symbol();
    }

    /** Number of decimal places in the minor unit (2 for all supported currencies). */
    public function minorUnits(): int
    {
        return 2;
    }
}
