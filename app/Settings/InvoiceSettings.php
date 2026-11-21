<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class InvoiceSettings extends Settings
{
    /** Number format: {prefix}-{YYYY}-{NNNN}, yearly reset. */
    public string $prefix;

    public string $default_currency;

    public int $default_due_days;

    /** Basis points: 0 = not VAT-registered. */
    public int $default_tax_rate_bp;

    public ?string $tax_note;

    public ?string $footer;

    public ?string $logo_path;

    public static function group(): string
    {
        return 'invoicing';
    }
}
