<?php

declare(strict_types=1);

use App\Actions\Invoicing\ChangeInvoiceStatus;
use App\Actions\Invoicing\EmailInvoice;
use App\Actions\Invoicing\GenerateInvoicePdf;
use App\Actions\Invoicing\IssueInvoice;
use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\InvoiceAction;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Mail::fake();
    $this->actingAs(adminWithMfa());
});

/** An invoice in the given status, issued for real when it is not a draft. */
function invoiceIn(InvoiceStatus $status): Invoice
{
    $invoice = Invoice::factory()->create(['issue_date' => now()->subDays(20), 'due_date' => now()->subDays(6)]);
    $invoice->items()->create(['position' => 0, 'description' => 'Work', 'quantity_milli' => 1000, 'unit_price_minor' => 10000]);
    if ($status === InvoiceStatus::Draft) {
        return $invoice->refresh();
    }
    app(IssueInvoice::class)($invoice);
    match ($status) {
        InvoiceStatus::Overdue => $invoice->forceFill(['status' => InvoiceStatus::Overdue])->save(),
        InvoiceStatus::Paid => app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Paid),
        InvoiceStatus::Cancelled => app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Cancelled),
        default => null,
    };

    return $invoice->refresh();
}

/** Header action names on the editor, by the matrix action they stand for. */
const BUTTONS = [
    'save' => InvoiceAction::Edit, 'issue' => InvoiceAction::Issue, 'generatePdf' => InvoiceAction::GeneratePdf,
    'download' => InvoiceAction::Download, 'email' => InvoiceAction::Email, 'markPaid' => InvoiceAction::MarkPaid,
    'cancel' => InvoiceAction::Cancel, 'duplicate' => InvoiceAction::Duplicate, 'delete' => InvoiceAction::Delete,
];

it('shows only the actions each status allows', function (InvoiceStatus $status, array $expected): void {
    $page = Livewire::test(EditInvoice::class, ['record' => invoiceIn($status)->getRouteKey()]);

    foreach (BUTTONS as $name => $action) {
        in_array($action, $expected, true) ? $page->assertActionVisible($name) : $page->assertActionHidden($name);
    }
    expect($status->availableActions())->toEqualCanonicalizing($expected);
})->with([
    'draft' => [InvoiceStatus::Draft, [InvoiceAction::Edit, InvoiceAction::Issue, InvoiceAction::Duplicate, InvoiceAction::Delete]],
    'sent' => [InvoiceStatus::Sent, [InvoiceAction::GeneratePdf, InvoiceAction::Download, InvoiceAction::Email, InvoiceAction::MarkPaid, InvoiceAction::Cancel, InvoiceAction::Duplicate]],
    'overdue' => [InvoiceStatus::Overdue, [InvoiceAction::GeneratePdf, InvoiceAction::Download, InvoiceAction::Email, InvoiceAction::MarkPaid, InvoiceAction::Cancel, InvoiceAction::Duplicate]],
    'paid' => [InvoiceStatus::Paid, [InvoiceAction::GeneratePdf, InvoiceAction::Download, InvoiceAction::Email, InvoiceAction::Duplicate]],
    'cancelled' => [InvoiceStatus::Cancelled, [InvoiceAction::Download, InvoiceAction::Duplicate]],
]);

it('refuses disallowed actions on the server, whatever the buttons show', function (InvoiceStatus $status): void {
    $invoice = invoiceIn($status);
    $attempts = [
        InvoiceAction::MarkPaid->value => fn () => app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Paid),
        InvoiceAction::Cancel->value => fn () => app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Cancelled),
        InvoiceAction::Email->value => fn () => app(EmailInvoice::class)($invoice, 'client@example.com', [], 'Invoice', 'Hello'),
        InvoiceAction::GeneratePdf->value => fn () => app(GenerateInvoicePdf::class)($invoice),
        InvoiceAction::Issue->value => fn () => app(IssueInvoice::class)($invoice),
    ];

    foreach ($attempts as $action => $attempt) {
        if (! $status->allows(InvoiceAction::from($action))) {
            expect($attempt)->toThrow(InvalidInvoice::class);
            $invoice->refresh();
        }
    }
})->with(InvoiceStatus::cases());

it('hides Mark as paid as soon as an invoice is paid', function (): void {
    $invoice = invoiceIn(InvoiceStatus::Sent);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('markPaid')
        ->assertActionHidden('markPaid')->assertActionHidden('cancel')->assertActionVisible('download');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
});

it('does not offer Mark as paid on a paid invoice, and the server refuses it too', function (): void {
    $invoice = invoiceIn(InvoiceStatus::Paid);
    $paidAt = $invoice->paid_at;

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])->assertActionHidden('markPaid');

    expect(fn () => app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Paid))->toThrow(InvalidInvoice::class)
        ->and($invoice->refresh()->paid_at?->equalTo($paidAt))->toBeTrue();
});
