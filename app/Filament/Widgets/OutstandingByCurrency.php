<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Unpaid totals per currency (never summed across currencies), as in the approved dashboard canvas.
 */
class OutstandingByCurrency extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Outstanding by currency';

    protected function getStats(): array
    {
        $rows = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->selectRaw('currency, status, count(*) as invoices, sum(total_minor) as total')
            ->groupBy('currency', 'status')
            ->toBase()->get();

        return collect(Currency::cases())->map(function (Currency $currency) use ($rows): Stat {
            $mine = $rows->where('currency', $currency->value);
            $count = (int) $mine->sum('invoices');
            $overdue = (int) $mine->where('status', InvoiceStatus::Overdue->value)->sum('invoices');

            return Stat::make($currency->value, Money::format((int) $mine->sum('total'), $currency))
                ->description($count === 0 ? 'Nothing outstanding' : $count.' '.str('invoice')->plural($count).' · '.$overdue.' overdue')
                ->color($overdue > 0 ? 'danger' : 'gray');
        })->all();
    }
}
