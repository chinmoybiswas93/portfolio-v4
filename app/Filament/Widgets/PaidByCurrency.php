<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Invoicing\Earnings;
use App\Enums\Currency;
use App\Enums\PaidPeriod;
use App\Support\Money;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * "Paid" per currency for a chosen period (ADR-0010), counted by payment date. Period tabs as in the list toolbars.
 */
class PaidByCurrency extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Paid';

    protected int|array|null $columns = 4;

    #[Url(as: 'paid')]
    public string $period = PaidPeriod::ThisYear->value;

    public function getSectionContentComponent(): Component
    {
        $tabs = collect(PaidPeriod::cases())->map(function (PaidPeriod $p): string {
            $selected = $p === $this->selectedPeriod();

            return sprintf(
                '<button type="button" role="tab" aria-selected="%s" wire:click="$set(\'period\', \'%s\')" class="cb-tab%s">%s</button>',
                $selected ? 'true' : 'false', $p->value, $selected ? ' cb-tab-active' : '', e($p->label()),
            );
        })->implode('');

        $section = parent::getSectionContentComponent();
        if ($section instanceof Section) {
            $section->afterHeader(new HtmlString('<div class="cb-tabs" role="tablist" aria-label="Paid period">'.$tabs.'</div>'));
        }

        return $section;
    }

    protected function getStats(): array
    {
        $period = $this->selectedPeriod();
        $paid = app(Earnings::class)->paid($period);

        return collect(Currency::cases())->map(function (Currency $currency) use ($paid, $period): Stat {
            ['total' => $total, 'count' => $count] = $paid[$currency->value];

            return Stat::make($currency->value, Money::format($total, $currency))
                ->description($count === 0 ? $period->emptyLabel() : $count.' '.str('invoice')->plural($count).' paid')
                ->color($count === 0 ? 'gray' : 'success');
        })->all();
    }

    private function selectedPeriod(): PaidPeriod
    {
        return PaidPeriod::tryFrom($this->period) ?? PaidPeriod::ThisYear;
    }
}
