<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class InvoiceMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invoice-text', with: ['body' => $this->bodyText]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdfPath)
                ->as($this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
