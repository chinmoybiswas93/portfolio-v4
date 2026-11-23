<?php

declare(strict_types=1);

use App\Actions\Invoicing\ChangeInvoiceStatus;
use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010: "Mark as paid" asks for the payment date, so a late-recorded payment counts in the right period.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-24 15:30:00');
    $this->actingAs(adminWithMfa());
    $this->invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'number' => 'INV-2026-0042', 'issue_date' => '2026-10-15']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('records an earlier payment day at midday UK time', function (): void {
    Livewire::test(EditInvoice::class, ['record' => $this->invoice->getRouteKey()])
        ->mountAction('markPaid')
        ->assertSchemaStateSet(['paid_on' => '2026-11-24'])
        ->fillForm(['paid_on' => '2026-10-31'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $invoice = $this->invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        // 31 Oct 2026 is GMT, so midday in London is 12:00 UTC.
        ->and($invoice->paid_at?->utc()->format('Y-m-d H:i'))->toBe('2026-10-31 12:00');
});

it('keeps the current time when paid today', function (): void {
    app(ChangeInvoiceStatus::class)($this->invoice, InvoiceStatus::Paid, CarbonImmutable::parse('2026-11-24'));

    expect($this->invoice->refresh()->paid_at?->utc()->format('Y-m-d H:i'))->toBe('2026-11-24 15:30');
});

it('refuses a payment date in the future or before the issue date', function (string $day, string $message): void {
    expect(fn () => app(ChangeInvoiceStatus::class)($this->invoice, InvoiceStatus::Paid, CarbonImmutable::parse($day)))
        ->toThrow(InvalidInvoice::class, $message);
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Sent);
})->with([
    'future' => ['2026-11-25', 'cannot be in the future'],
    'before issue' => ['2026-10-14', 'cannot be before the issue date'],
]);

it('validates the payment date in the dialog', function (): void {
    Livewire::test(EditInvoice::class, ['record' => $this->invoice->getRouteKey()])
        ->mountAction('markPaid')
        ->fillForm(['paid_on' => '2026-12-01'])
        ->callMountedAction()
        ->assertHasFormErrors(['paid_on']);

    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Sent);
});
