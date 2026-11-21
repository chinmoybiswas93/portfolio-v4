<?php

declare(strict_types=1);

namespace App\Support\Markdown;

final class ReadingTime
{
    public const WORDS_PER_MINUTE = 220;

    public static function minutes(?string $markdown): int
    {
        $text = strip_tags((string) $markdown);
        $words = str_word_count($text);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
