<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Business and payment details printed on invoices. Entered by the owner in admin only;
 * every value is encrypted at rest.
 */
final class PaymentSettings extends Settings
{
    public ?string $business_name;

    public ?string $business_address;

    public ?string $business_email;

    public ?string $bank_details;

    public ?string $wise_details;

    public ?string $paypal_email;

    public static function group(): string
    {
        return 'payment';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['business_name', 'business_address', 'business_email', 'bank_details', 'wise_details', 'paypal_email'];
    }
}
