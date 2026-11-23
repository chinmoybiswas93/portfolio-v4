<?php

declare(strict_types=1);

use App\Domain\Invoicing\Earnings;
use App\Enums\InvoiceStatus;
use App\Enums\PaidPeriod;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-24 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function paidInvoice(string $currency, int $total, string $paidAt): Invoice
{
    return Invoice::factory()->create(['currency' => $currency, 'status' => InvoiceStatus::Paid, 'total_minor' => $total, 'paid_at' => $paidAt]);
}

it('bounds each period in UK time', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 12:00:00');

    expect(PaidPeriod::ThisMonth->range($now))->toBe(['2026-11-01 00:00:00', '2026-11-30 23:59:59'])
        ->and(PaidPeriod::ThisYear->range($now))->toBe(['2026-01-01 00:00:00', '2026-12-31 23:59:59'])
        // 1 Jan 2025 00:00 in London is 00:00 UTC (GMT); BST months shift by an hour.
        ->and(PaidPeriod::LastYear->range($now))->toBe(['2025-01-01 00:00:00', '2025-12-31 23:59:59'])
        ->and(PaidPeriod::AllTime->range($now))->toBeNull();
    expect(PaidPeriod::ThisMonth->range(CarbonImmutable::parse('2026-07-10 12:00:00')))->toBe(['2026-06-30 23:00:00', '2026-07-31 22:59:59']);
});

it('sums paid invoices per currency within the period, never across currencies', function (): void {
    paidInvoice('GBP', 120000, '2026-11-03 10:00:00');
    paidInvoice('GBP', 30050, '2026-02-01 09:00:00');
    paidInvoice('EUR', 50000, '2026-11-20 09:00:00');
    paidInvoice('GBP', 99900, '2025-12-31 23:30:00'); // last year
    Invoice::factory()->create(['currency' => 'GBP', 'status' => InvoiceStatus::Sent, 'total_minor' => 777]);

    $month = app(Earnings::class)->paid(PaidPeriod::ThisMonth);
    $year = app(Earnings::class)->paid(PaidPeriod::ThisYear);
    $last = app(Earnings::class)->paid(PaidPeriod::LastYear);
    $all = app(Earnings::class)->paid(PaidPeriod::AllTime);

    expect($month['GBP'])->toBe(['total' => 120000, 'count' => 1])
        ->and($month['EUR'])->toBe(['total' => 50000, 'count' => 1])
        ->and($month['USD'])->toBe(['total' => 0, 'count' => 0])
        ->and($year['GBP'])->toBe(['total' => 150050, 'count' => 2])
        ->and($last['GBP'])->toBe(['total' => 99900, 'count' => 1])
        ->and($all['GBP'])->toBe(['total' => 249950, 'count' => 3])
        ->and(array_keys($all))->toBe(['GBP', 'EUR', 'USD', 'BDT']);
});

it('sums open invoices per currency with the overdue part', function (): void {
    Invoice::factory()->create(['currency' => 'GBP', 'status' => InvoiceStatus::Sent, 'total_minor' => 150000]);
    Invoice::factory()->create(['currency' => 'GBP', 'status' => InvoiceStatus::Overdue, 'total_minor' => 65000]);
    Invoice::factory()->create(['currency' => 'EUR', 'status' => InvoiceStatus::Sent, 'total_minor' => 80000]);
    Invoice::factory()->create(['currency' => 'GBP', 'status' => InvoiceStatus::Draft, 'total_minor' => 1]);
    Invoice::factory()->create(['currency' => 'GBP', 'status' => InvoiceStatus::Cancelled, 'total_minor' => 2]);
    paidInvoice('GBP', 3, '2026-11-01 10:00:00');

    $open = app(Earnings::class)->open();

    expect($open['GBP'])->toBe(['total' => 215000, 'count' => 2, 'overdue_total' => 65000, 'overdue_count' => 1])
        ->and($open['EUR'])->toBe(['total' => 80000, 'count' => 1, 'overdue_total' => 0, 'overdue_count' => 0])
        ->and($open['BDT'])->toBe(['total' => 0, 'count' => 0, 'overdue_total' => 0, 'overdue_count' => 0]);
});
