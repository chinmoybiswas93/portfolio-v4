<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Periods for the dashboard's "Paid" totals (ADR-0010). Boundaries follow UK time; returned in UTC (storage).
 */
enum PaidPeriod: string
{
    case ThisMonth = 'this_month';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case AllTime = 'all_time';

    public const string TIMEZONE = 'Europe/London';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => 'This month',
            self::ThisYear => 'This year',
            self::LastYear => 'Last year',
            self::AllTime => 'All time',
        };
    }

    /** Wording for an empty card, e.g. "None this year". */
    public function emptyLabel(): string
    {
        return match ($this) {
            self::AllTime => 'None yet',
            default => 'None '.lcfirst($this->label()),
        };
    }

    /**
     * @return array{0: string, 1: string}|null inclusive UTC bounds, or null for all time
     */
    public function range(CarbonImmutable $now): ?array
    {
        $local = $now->setTimezone(self::TIMEZONE);

        [$start, $end] = match ($this) {
            self::ThisMonth => [$local->startOfMonth(), $local->endOfMonth()],
            self::ThisYear => [$local->startOfYear(), $local->endOfYear()],
            self::LastYear => [$local->subYearNoOverflow()->startOfYear(), $local->subYearNoOverflow()->endOfYear()],
            self::AllTime => [null, null],
        };

        if ($start === null || $end === null) {
            return null;
        }

        return [$start->utc()->format('Y-m-d H:i:s'), $end->utc()->format('Y-m-d H:i:s')];
    }
}
