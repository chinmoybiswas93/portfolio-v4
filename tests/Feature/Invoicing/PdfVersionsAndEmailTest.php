<?php

declare(strict_types=1);

use App\Actions\Invoicing\EmailInvoice;
use App\Actions\Invoicing\GenerateInvoicePdf;
use App\Actions\Invoicing\IssueInvoice;
use App\Enums\EmailStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Http\Controllers\Site\InvoiceLinkController;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function issuedInvoice(): Invoice
{
    $invoice = Invoice::factory()->create(['reference' => 'Care plan']);
    $invoice->items()->create(['position' => 0, 'description' => 'Work', 'quantity_milli' => 1000, 'unit_price_minor' => 10000]);

    return app(IssueInvoice::class)($invoice)->refresh();
}

it('keeps every PDF version and never overwrites one', function (): void {
    $invoice = issuedInvoice();
    $first = (string) Storage::disk('local')->get('invoices/2026/'.$invoice->number.'-v1.pdf');

    app(GenerateInvoicePdf::class)($invoice);
    app(GenerateInvoicePdf::class)($invoice);

    expect($invoice->pdfs()->pluck('version')->all())->toBe([3, 2, 1])
        ->and($invoice->refresh()->pdf_path)->toEndWith('-v3.pdf')
        ->and((string) Storage::disk('local')->get('invoices/2026/'.$invoice->number.'-v1.pdf'))->toBe($first);
    foreach ($invoice->pdfs as $pdf) {
        expect(hash('sha256', (string) Storage::disk('local')->get($pdf->path)))->toBe($pdf->sha256);
    }
});

it('queues the email as Pending with the exact version, then marks it Sent', function (): void {
    Queue::fake();
    $invoice = issuedInvoice();
    app(GenerateInvoicePdf::class)($invoice);

    $log = app(EmailInvoice::class)($invoice, 'client@example.com', [], 'Invoice', 'Hi');

    expect($log->status)->toBe(EmailStatus::Pending)->and($log->pdf?->version)->toBe(2);
    Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job): bool => $job->emailId === $log->id);

    Mail::fake();
    (new SendInvoiceEmail($log->id))->handle();
    expect($log->refresh()->status)->toBe(EmailStatus::Sent)->and($log->sent_at)->not->toBeNull();
    Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->pdf->is($log->pdf));
});

it('records a failed send with the error and does not send twice', function (): void {
    Queue::fake();
    $log = app(EmailInvoice::class)(issuedInvoice(), 'client@example.com', [], 'Invoice', 'Hi');
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP 535 authentication failed'));

    (new SendInvoiceEmail($log->id))->handle();
    (new SendInvoiceEmail($log->id))->handle();

    expect($log->refresh()->status)->toBe(EmailStatus::Failed)->and($log->error)->toContain('SMTP 535');
});

it('builds an informative email with the summary, payment methods, signed link and the attached version', function (): void {
    $invoice = issuedInvoice();
    $invoice->forceFill(['seller_snapshot' => ['name' => 'Chinmoy Biswas', 'email' => 'hello@example.com', 'bank_details' => 'IBAN X', 'paypal_email' => 'p@example.com']])->save();
    $pdf = $invoice->pdfs()->firstOrFail();

    $mail = new InvoiceMail($invoice, $pdf, 'Invoice '.$invoice->number, "Hi Jane,\nHere it is.");

    $mail->assertSeeInHtml($invoice->number)
        ->assertSeeInHtml('Amount due')->assertSeeInHtml('£100.00')->assertSeeInHtml('GBP')
        ->assertSeeInHtml('Care plan')->assertSeeInHtml('Bank transfer · PayPal')
        ->assertSeeInHtml('View invoice')->assertSeeInHtml('signature=', false)
        ->assertSeeInText('Amount due: £100.00 GBP')->assertSeeInText('valid for 30 days');

    $attachments = $mail->attachments();
    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->as)->toBe($invoice->number.'.pdf')
        ->and($attachments[0]->mime)->toBe('application/pdf');
});

it('serves one PDF version through a signed link, and refuses expired, tampered and other-invoice links', function (): void {
    $invoice = issuedInvoice();
    $other = issuedInvoice();
    $pdf = $invoice->pdfs()->firstOrFail();
    $url = InvoiceLinkController::signedUrl($pdf);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeaderMissing('Set-Cookie');

    $otherId = $other->pdfs()->firstOrFail()->id;
    $this->get(str_replace('/invoice/'.$pdf->id.'?', '/invoice/'.$otherId.'?', $url))->assertForbidden();
    $this->get($url.'x')->assertForbidden();
    $this->get(route('invoices.link', ['pdf' => $pdf->id]))->assertForbidden();

    $this->travel(InvoiceLinkController::VALID_DAYS + 1)->days();
    $this->get($url)->assertForbidden();
});

it('stops serving the link once the invoice is cancelled', function (): void {
    $invoice = issuedInvoice();
    $url = InvoiceLinkController::signedUrl($invoice->pdfs()->firstOrFail());
    $this->get($url)->assertOk();

    $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();
    $this->get($url)->assertStatus(410);
});

it('previews PDF versions for admins only', function (): void {
    $invoice = issuedInvoice();
    $pdf = $invoice->pdfs()->firstOrFail();
    $path = adminPath("/invoices/{$invoice->id}/pdfs/{$pdf->id}");

    $this->get($path)->assertRedirect(adminPath('/login'));
    $this->actingAs(User::factory()->create(['is_admin' => false]))->get($path)->assertForbidden();

    $this->actingAs(adminWithMfa())->get($path)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $otherPdf = issuedInvoice()->pdfs()->firstOrFail();
    $this->get(adminPath("/invoices/{$invoice->id}/pdfs/{$otherPdf->id}"))->assertNotFound();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])->assertActionVisible('preview');
    expect(view('filament.invoice-preview', ['invoice' => $invoice])->render())
        ->toContain('Versions')->toContain('v1')->toContain('/pdfs/'.$pdf->id);
});
