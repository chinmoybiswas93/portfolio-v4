<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\EmailStatus;
use App\Enums\InvoiceAction;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails the PDF synchronously (Hostinger SMTP) so the admin sees success or the error at once (ADR-0006).
 * Every attempt is logged; the exact PDF sent is copied to an immutable path with its hash (ADR-0004).
 */
final class EmailInvoice
{
    public function __construct(private readonly GenerateInvoicePdf $generatePdf) {}

    /**
     * @param  list<string>  $cc
     */
    public function __invoke(Invoice $invoice, string $to, array $cc, string $subject, string $body, ?User $sender = null): InvoiceEmail
    {
        if (! $invoice->status->allows(InvoiceAction::Email)) {
            throw new InvalidInvoice('Only issued invoices can be emailed.');
        }

        $disk = Storage::disk(GenerateInvoicePdf::DISK);
        if ($invoice->pdf_path === null || ! $disk->exists($invoice->pdf_path)) {
            ($this->generatePdf)($invoice);
        }

        $log = new InvoiceEmail([
            'invoice_id' => $invoice->getKey(),
            'sent_by' => $sender?->getKey(),
            'to_email' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailStatus::Failed,
        ]);
        $log->save();

        $copy = sprintf('invoices/%d/sent/%s-%d.pdf', $invoice->sequence_year, $invoice->number, $log->getKey());
        $disk->copy((string) $invoice->pdf_path, $copy);
        $log->forceFill(['pdf_path' => $copy, 'pdf_sha256' => hash('sha256', (string) $disk->get($copy))])->save();

        try {
            $sent = Mail::to($to)->cc($cc)->send(new InvoiceMail($invoice, $subject, $body, $copy));
            $log->forceFill([
                'status' => EmailStatus::Sent,
                'message_id' => $sent?->getMessageId(),
                'sent_at' => now(),
                'error' => null,
            ])->save();
            $invoice->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);
            $log->forceFill(['error' => Str::limit($e->getMessage(), 500)])->save();
        }

        return $log;
    }
}
