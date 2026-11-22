<?php

declare(strict_types=1);

use App\Actions\Invoicing\ChangeInvoiceStatus;
use App\Actions\Invoicing\DuplicateInvoice;
use App\Actions\Invoicing\EmailInvoice;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\TaxRules;
use App\Enums\DiscountType;
use App\Enums\EmailStatus;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Settings\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/** Draft invoice with the canvas sample lines: £350, 6 × £45, 2.5 × £45, 5% discount. */
function sampleInvoice(array $client = [], array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->for(Client::factory()->create($client))->create($attributes + [
        'issue_date' => '2026-11-22', 'due_date' => '2026-12-06',
        'discount_type' => DiscountType::Percent, 'discount_value' => 500,
    ]);
    foreach ([[1000, 35000, "Website care plan — November 2026\nUpdates, backups, uptime and security checks"], [6000, 4500, 'Custom Gutenberg block'], [2500, 4500, 'Plugin updates']] as $i => [$qty, $price, $text]) {
        InvoiceItem::factory()->for($invoice)->create(['position' => $i, 'quantity_milli' => $qty, 'unit_price_minor' => $price, 'description' => $text]);
    }

    return app(RecalculateInvoice::class)($invoice);
}

it('recalculates draft totals from its lines', function (): void {
    $invoice = sampleInvoice();

    expect($invoice->subtotal_minor)->toBe(73250)
        ->and($invoice->discount_minor)->toBe(3663)
        ->and($invoice->total_minor)->toBe(69587)
        ->and($invoice->items->pluck('amount_minor')->all())->toBe([35000, 27000, 11250]);
});

it('issues gap-free numbers per year and resets in a new year', function (): void {
    $a = app(IssueInvoice::class)(sampleInvoice());
    $b = app(IssueInvoice::class)(sampleInvoice());
    $c = app(IssueInvoice::class)(sampleInvoice(attributes: ['issue_date' => '2027-01-03', 'due_date' => null]));

    expect([$a->number, $b->number, $c->number])->toBe(['INV-2026-0001', 'INV-2026-0002', 'INV-2027-0001'])
        ->and($a->status)->toBe(InvoiceStatus::Sent)
        ->and($c->due_date?->toDateString())->toBe('2027-01-17');
});

it('freezes client and seller details and locks the invoice', function (): void {
    $payment = app(PaymentSettings::class);
    $payment->business_tax_id = 'BD-TIN-123';
    $payment->bank_details = "Account: Test\nIBAN: GB00TEST";
    $payment->save();

    $invoice = app(IssueInvoice::class)(sampleInvoice(['company_name' => 'Acme Ltd', 'country_code' => 'GB', 'vat_number' => 'GB123456789']));
    $invoice->client->update(['company_name' => 'Renamed Ltd']);

    expect($invoice->client_snapshot['company_name'])->toBe('Acme Ltd')
        ->and($invoice->client_snapshot['tax_id_label'])->toBe('VAT reg. no.')
        ->and($invoice->seller_snapshot['tax_id'])->toBe('BD-TIN-123')
        ->and($invoice->status->isLocked())->toBeTrue();

    app(RecalculateInvoice::class)($invoice);
})->throws(InvalidInvoice::class);

it('prints the reverse charge note for EU businesses at 0% and the default note otherwise', function (): void {
    $eu = app(IssueInvoice::class)(sampleInvoice(['country_code' => 'DE', 'vat_number' => 'DE123456789']));
    $us = app(IssueInvoice::class)(sampleInvoice(['country_code' => 'US', 'vat_number' => '12-3456789']));

    expect($eu->reverse_charge)->toBeTrue()->and($eu->tax_note)->toBe(TaxRules::REVERSE_CHARGE_NOTE)
        ->and($us->reverse_charge)->toBeFalse()->and($us->tax_note)->toBe('VAT not applicable');
});

it('refuses to issue an empty or already issued invoice', function (): void {
    $empty = Invoice::factory()->create();
    expect(fn () => app(IssueInvoice::class)($empty))->toThrow(InvalidInvoice::class);

    $issued = app(IssueInvoice::class)(sampleInvoice());
    expect(fn () => app(IssueInvoice::class)($issued))->toThrow(InvalidInvoice::class);
});

it('stores a PDF on the private disk with the right content', function (): void {
    $invoice = app(IssueInvoice::class)(sampleInvoice(['company_name' => 'Acme Ltd', 'country_code' => 'GB', 'vat_number' => 'GB123456789']));

    Storage::disk('local')->assertExists('invoices/2026/INV-2026-0001.pdf');
    expect($invoice->pdf_sha256)->toBe(hash('sha256', (string) Storage::disk('local')->get($invoice->pdf_path)));

    $text = (new Parser)->parseContent((string) Storage::disk('local')->get($invoice->pdf_path))->getText();
    expect($text)->toContain('INV-2026-0001')->toContain('Acme Ltd')->toContain('£695.87')
        ->toContain('VAT reg. no.: GB123456789')->toContain('Discount (5%)')->not->toContain('DRAFT');

    // Set in the bundled Inter (Regular, Medium, SemiBold); no remote fonts.
    $fonts = collect((new Parser)->parseContent((string) Storage::disk('local')->get($invoice->pdf_path))->getFonts())->map->getName()->unique()->values()->all();
    expect($fonts)->toContain('Inter-Regular')->toContain('Inter-Medium')->toContain('Inter-SemiBold')
        ->and(config('dompdf.options.enable_remote'))->toBeFalse();
});

it('emails the PDF, keeps an immutable copy and logs the send', function (): void {
    Mail::fake();
    $invoice = app(IssueInvoice::class)(sampleInvoice(['email' => 'client@example.com']));

    $log = app(EmailInvoice::class)($invoice, 'client@example.com', ['accounts@example.com'], 'Invoice INV-2026-0001', 'Hello');

    Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('client@example.com') && $mail->hasCc('accounts@example.com'));
    expect($log->status)->toBe(EmailStatus::Sent)
        ->and($log->pdf_path)->toBe("invoices/2026/sent/INV-2026-0001-{$log->id}.pdf")
        ->and($log->pdf_sha256)->toBe($invoice->pdf_sha256)
        ->and($invoice->refresh()->sent_at)->not->toBeNull();
    Storage::disk('local')->assertExists($log->pdf_path);
});

it('logs a failed send with the error and leaves the invoice unsent', function (): void {
    $invoice = app(IssueInvoice::class)(sampleInvoice());
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));

    $log = app(EmailInvoice::class)($invoice, 'client@example.com', [], 'Invoice', 'Hello');

    expect($log->status)->toBe(EmailStatus::Failed)
        ->and($log->error)->toContain('SMTP connection refused')
        ->and($invoice->refresh()->sent_at)->toBeNull();
});

it('never emails drafts', function (): void {
    app(EmailInvoice::class)(sampleInvoice(), 'client@example.com', [], 'Invoice', 'Hello');
})->throws(InvalidInvoice::class);

it('marks only sent invoices past their due date as overdue', function (): void {
    $late = app(IssueInvoice::class)(sampleInvoice(attributes: ['issue_date' => now()->subDays(30)->toDateString(), 'due_date' => now()->subDay()->toDateString()]));
    $current = app(IssueInvoice::class)(sampleInvoice(attributes: ['issue_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString()]));
    $paid = app(IssueInvoice::class)(sampleInvoice(attributes: ['issue_date' => now()->subDays(30)->toDateString(), 'due_date' => now()->subDay()->toDateString()]));
    app(ChangeInvoiceStatus::class)($paid, InvoiceStatus::Paid);

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($late->refresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($current->refresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($paid->refresh()->status)->toBe(InvoiceStatus::Paid);
});

it('enforces status transitions when paying and cancelling', function (): void {
    $invoice = app(IssueInvoice::class)(sampleInvoice());
    app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Paid);

    expect($invoice->paid_at)->not->toBeNull();
    app(ChangeInvoiceStatus::class)($invoice, InvoiceStatus::Cancelled);
})->throws(InvalidInvoice::class);

it('duplicates an invoice as a new draft', function (): void {
    $source = app(IssueInvoice::class)(sampleInvoice());

    $copy = app(DuplicateInvoice::class)($source->load('items'));

    expect($copy->status)->toBe(InvoiceStatus::Draft)
        ->and($copy->number)->toBeNull()
        ->and($copy->items)->toHaveCount(3)
        ->and($copy->total_minor)->toBe($source->total_minor);
});

it('totals and prints a BDT invoice', function (): void {
    $invoice = sampleInvoice(['company_name' => 'Dhaka Shop', 'country_code' => 'BD', 'default_currency' => 'BDT'], ['currency' => 'BDT']);
    expect($invoice->total_minor)->toBe(69587);

    $issued = app(IssueInvoice::class)($invoice);
    $text = (new Parser)->parseContent((string) Storage::disk('local')->get($issued->pdf_path))->getText();

    expect($text)->toContain('BDT 695.87')->toContain('BDT 350.00')->toContain('−BDT 36.63')->not->toContain('৳');
});
