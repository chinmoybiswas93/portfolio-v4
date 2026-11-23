<?php

declare(strict_types=1);

namespace App\Filament\Support;

use ResourceBundle;

/**
 * ISO 3166-1 alpha-2 country options with English names (from ICU via the intl extension).
 */
final class Countries
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        static $options = null;
        if ($options !== null) {
            return $options;
        }

        $codes = [];
        $bundle = ResourceBundle::create('en', 'ICUDATA-region');
        foreach ($bundle?->get('Countries') ?? [] as $code => $name) {
            if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['EU', 'EZ', 'UN', 'ZZ', 'XA', 'XB', 'QO'], true)) {
                $codes[$code] = (string) $name;
            }
        }
        asort($codes);

        return $options = $codes;
    }
}
