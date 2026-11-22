<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Currency;

/**
 * Formats integer minor units for display (no floats): 123456 GBP -> "£1,234.56", -3663 -> "−£36.63".
 */
final class Money
{
    public static function format(int $minor, Currency $currency): string
    {
        $sign = $minor < 0 ? '−' : '';
        $abs = abs($minor);
        $major = number_format(intdiv($abs, 100));
        $cents = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $sign.$currency->symbol().$major.'.'.$cents;
    }

    /** "12.5" -> 1250 for scale 2. Rejects anything but a plain non-negative decimal. Returns null when invalid. */
    public static function parseDecimal(string|int|float|null $value, int $scale): ?int
    {
        $text = trim(str_replace(',', '', (string) $value));
        if (preg_match('/^\d{1,13}(\.\d+)?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        if (strlen($fraction) > $scale) {
            return null;
        }

        return (int) $whole * (10 ** $scale) + (int) str_pad($fraction, $scale, '0');
    }

    /** 1250 at scale 2 -> "12.50"; at scale 3 trailing zeros are trimmed (1500 -> "1.5"). */
    public static function toDecimalString(int $value, int $scale, bool $trim = false): string
    {
        $divisor = 10 ** $scale;
        $text = intdiv($value, $divisor).'.'.str_pad((string) ($value % $divisor), $scale, '0', STR_PAD_LEFT);

        return $trim ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
