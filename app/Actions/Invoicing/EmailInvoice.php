<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\EmailStatus;
use App\Enums\InvoiceAction;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\InvoiceEmail;
use App\Models\InvoicePdf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Queues an invoice email: logged as Pending with the exact PDF version attached, then sent by SendInvoiceEmail
 * (Sent = accepted by the mail server, Failed = with the error).
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
        /** @var InvoicePdf $version */
        $version = $invoice->pdfs()->firstOrFail();

        // Logged as Pending with the exact PDF version it will attach; the queue sends it (cron, every minute).
        $log = new InvoiceEmail([
            'invoice_id' => $invoice->getKey(),
            'invoice_pdf_id' => $version->getKey(),
            'sent_by' => $sender?->getKey(),
            'to_email' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailStatus::Pending,
        ]);
        $log->forceFill(['pdf_path' => $version->path, 'pdf_sha256' => $version->sha256])->save();

        SendInvoiceEmail::dispatch($log->getKey());

        return $log;
    }
}
