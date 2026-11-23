<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;

/**
 * Shared image conversions, called from each model's registerMediaConversions() (ADR-0005): an instant admin thumb, queued responsive card/hero sizes, all WebP.
 */
trait HasResponsiveImages
{
    protected function registerResponsiveConversions(): void
    {
        // Conversion options first; image manipulations (format, fit) last in each chain.
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Crop, 400, 250);

        $this->addMediaConversion('card')
            ->withResponsiveImages()
            ->format('webp')
            ->fit(Fit::Max, 800, 800);

        $this->addMediaConversion('hero')
            ->withResponsiveImages()
            ->format('webp')
            ->fit(Fit::Max, 1600, 1600);
    }
}
