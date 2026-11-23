<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EmailStatus;
use App\Mail\InvoiceMail;
use App\Models\InvoiceEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one logged invoice email. Sent means accepted by the SMTP server, not delivered or opened.
 * A failure is recorded with its error and not retried automatically (the admin can send again).
 */
final class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $emailId) {}

    public function handle(): void
    {
        $log = InvoiceEmail::query()->with(['invoice', 'pdf'])->find($this->emailId);
        if ($log === null || $log->status !== EmailStatus::Pending || $log->pdf === null) {
            return;
        }

        try {
            $sent = Mail::to($log->to_email)->cc($log->cc ?? [])->send(new InvoiceMail($log->invoice, $log->pdf, $log->subject, (string) $log->body));
            $log->forceFill(['status' => EmailStatus::Sent, 'message_id' => $sent?->getMessageId(), 'sent_at' => now(), 'error' => null])->save();
            $log->invoice->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);
            $log->forceFill(['status' => EmailStatus::Failed, 'error' => Str::limit($e->getMessage(), 500)])->save();
        }
    }
}
