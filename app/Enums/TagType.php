<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TagType: string implements HasColor, HasLabel
{
    /** Blog topics, shown as tag archives. */
    case Topic = 'topic';

    /** Technology stack on projects and experience. */
    case Tech = 'tech';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Topic => 'info',
            self::Tech => 'primary',
        };
    }
}
