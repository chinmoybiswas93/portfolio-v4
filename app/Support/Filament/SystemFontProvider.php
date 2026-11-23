<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\FontProviders\Contracts\FontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Loads no web font: the admin uses the system font stack set in the admin theme (as the public site).
 */
final class SystemFontProvider implements FontProvider
{
    public function getHtml(string $family, ?string $url = null): Htmlable
    {
        return new HtmlString('');
    }
}
