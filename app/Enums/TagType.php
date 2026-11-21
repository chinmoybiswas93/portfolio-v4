<?php

declare(strict_types=1);

namespace App\Enums;

enum TagType: string
{
    /** Blog topics, shown as tag archives. */
    case Topic = 'topic';

    /** Technology stack on projects and experience. */
    case Tech = 'tech';
}
