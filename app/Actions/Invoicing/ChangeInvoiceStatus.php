<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\InvoiceAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaidPeriod;
use App\Models\Invoice;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Status changes after issue (paid, overdue, cancelled), guarded by InvoiceStatus::canTransitionTo().
 * A payment can be recorded for an earlier day (ADR-0010): today keeps the current time, an earlier day is stored
 * as midday UK time on that day, so it always lands in the right month and year.
 */
final class ChangeInvoiceStatus
{
    public function __invoke(Invoice $invoice, InvoiceStatus $to, ?DateTimeInterface $paidOn = null): Invoice
    {
        $action = match ($to) {
            InvoiceStatus::Paid => InvoiceAction::MarkPaid,
            InvoiceStatus::Cancelled => InvoiceAction::Cancel,
            default => null,
        };
        if (! $invoice->status->canTransitionTo($to) || ($action !== null && ! $invoice->status->allows($action))) {
            throw new InvalidInvoice("An invoice that is {$invoice->status->getLabel()} cannot become {$to->getLabel()}.");
        }

        $paidAt = $to === InvoiceStatus::Paid ? $this->paidAt($invoice, $paidOn) : null;

        $invoice->status = $to;
        match ($to) {
            InvoiceStatus::Paid => $invoice->paid_at = $paidAt,
            InvoiceStatus::Cancelled => $invoice->cancelled_at = now(),
            default => null,
        };
        $invoice->save();

        return $invoice;
    }

    private function paidAt(Invoice $invoice, ?DateTimeInterface $paidOn): Carbon
    {
        $now = Carbon::now();
        if ($paidOn === null) {
            return $now;
        }

        $day = Carbon::parse($paidOn->format('Y-m-d'), PaidPeriod::TIMEZONE);
        $today = $now->copy()->setTimezone(PaidPeriod::TIMEZONE)->startOfDay();
        if ($day->greaterThan($today)) {
            throw new InvalidInvoice('The payment date cannot be in the future.');
        }
        if ($invoice->issue_date !== null && $day->lessThan(Carbon::parse($invoice->issue_date->format('Y-m-d'), PaidPeriod::TIMEZONE))) {
            throw new InvalidInvoice('The payment date cannot be before the issue date.');
        }

        return $day->equalTo($today) ? $now : $day->setTime(12, 0)->utc();
    }
}
