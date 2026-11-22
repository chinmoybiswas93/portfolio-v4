<?php

declare(strict_types=1);

namespace App\Mail;

use App\Http\Controllers\Site\InvoiceLinkController;
use App\Models\Invoice;
use App\Models\InvoicePdf;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invoice email (canvas "Invoice-Email"): summary, personal message, a "View invoice" signed link to this exact
 * PDF version (30 days) and the same PDF attached.
 */
final class InvoiceMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoicePdf $pdf,
        public readonly string $subjectLine,
        public readonly string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        $invoice = $this->invoice;
        $seller = $invoice->seller_snapshot ?? [];
        $client = $invoice->client_snapshot ?? [];
        $methods = collect(['bank_details' => 'Bank transfer', 'wise_details' => 'Wise', 'paypal_email' => 'PayPal'])
            ->filter(fn (string $label, string $key): bool => trim((string) ($seller[$key] ?? '')) !== '')->values()->all();

        return new Content(
            view: 'mail.invoice',
            text: 'mail.invoice-text',
            with: [
                'number' => (string) $invoice->number,
                'client' => (string) ($client['company_name'] ?? ''),
                'issued' => $invoice->issue_date?->format('j M Y') ?? '',
                'due' => $invoice->due_date?->format('j M Y') ?? '',
                'amount' => Money::format($invoice->total_minor, $invoice->currency),
                'currency' => $invoice->currency->value,
                'reference' => $invoice->reference,
                'messageText' => $this->messageText,
                'methods' => $methods,
                'viewUrl' => InvoiceLinkController::signedUrl($this->pdf),
                'sellerName' => (string) ($seller['name'] ?? ''),
                'sellerTitle' => (string) ($seller['title'] ?? ''),
                'sellerEmail' => (string) ($seller['email'] ?? ''),
                'siteHost' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdf->path)
                ->as($this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
