<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaidPeriod;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Dashboard money totals (ADR-0010), per currency and never summed across currencies. Amounts are integer minor
 * units. Every currency is always present, in Currency::cases() order.
 */
final class Earnings
{
    /**
     * Paid invoices whose payment date falls in the period.
     *
     * @return array<string, array{total: int, count: int}>
     */
    public function paid(PaidPeriod $period): array
    {
        $query = Invoice::query()->where('status', InvoiceStatus::Paid);
        if ($range = $period->range(CarbonImmutable::now())) {
            $query->whereBetween('paid_at', $range);
        }
        $rows = $query->selectRaw('currency, count(*) as invoices, sum(total_minor) as total')->groupBy('currency')
            ->toBase()->get()->keyBy('currency');

        $result = [];
        foreach (Currency::cases() as $currency) {
            $row = $rows->get($currency->value);
            $result[$currency->value] = ['total' => (int) ($row->total ?? 0), 'count' => (int) ($row->invoices ?? 0)];
        }

        return $result;
    }

    /**
     * Everything still to be paid (Sent and Overdue), with the overdue part.
     *
     * @return array<string, array{total: int, count: int, overdue_total: int, overdue_count: int}>
     */
    public function open(): array
    {
        $rows = Invoice::query()->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->selectRaw('currency, status, count(*) as invoices, sum(total_minor) as total')->groupBy('currency', 'status')
            ->toBase()->get();

        $result = [];
        foreach (Currency::cases() as $currency) {
            $mine = $rows->where('currency', $currency->value);
            $overdue = $mine->where('status', InvoiceStatus::Overdue->value);
            $result[$currency->value] = [
                'total' => (int) $mine->sum('total'),
                'count' => (int) $mine->sum('invoices'),
                'overdue_total' => (int) $overdue->sum('total'),
                'overdue_count' => (int) $overdue->sum('invoices'),
            ];
        }

        return $result;
    }
}
