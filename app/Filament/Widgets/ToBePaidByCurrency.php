<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Invoicing\Earnings;
use App\Enums\Currency;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "To be paid" per currency (ADR-0010): every Sent and Overdue invoice, whatever its date, with the overdue part.
 */
class ToBePaidByCurrency extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'To be paid';

    protected ?string $description = 'All open invoices (Sent and Overdue).';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $open = app(Earnings::class)->open();

        return collect(Currency::cases())->map(function (Currency $currency) use ($open): Stat {
            $row = $open[$currency->value];
            $description = match (true) {
                $row['count'] === 0 => 'Nothing open',
                $row['overdue_count'] === 0 => $row['count'].' open · none overdue',
                default => $row['count'].' open · '.Money::format($row['overdue_total'], $currency).' overdue',
            };

            return Stat::make($currency->value, Money::format($row['total'], $currency))
                ->description($description)
                ->color($row['overdue_count'] > 0 ? 'danger' : 'gray');
        })->all();
    }
}
