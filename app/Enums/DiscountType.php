<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscountType: string
{
    case None = 'none';

    /** Value in basis points (1000 = 10%). */
    case Percent = 'percent';

    /** Value in minor units of the invoice currency. */
    case Fixed = 'fixed';
}
