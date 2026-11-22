<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\TaxRules;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Settings\InvoiceSettings;
use App\Settings\PaymentSettings;
use App\Settings\ProfileSettings;
use Illuminate\Support\Facades\DB;

/**
 * Finalises a draft: gap-free number (PREFIX-YYYY-NNNN, yearly sequence under a row lock), frozen client and
 * seller details, the tax note, then the stored PDF. Issued invoices are locked (docs/ARCHITECTURE.md §3).
 */
final class IssueInvoice
{
    public function __construct(
        private readonly RecalculateInvoice $recalculate,
        private readonly GenerateInvoicePdf $generatePdf,
        private readonly InvoiceSettings $invoicing,
        private readonly PaymentSettings $payment,
        private readonly ProfileSettings $profile,
    ) {}

    public function __invoke(Invoice $invoice): Invoice
    {
        DB::transaction(function () use ($invoice): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== InvoiceStatus::Draft) {
                throw new InvalidInvoice('Only draft invoices can be issued.');
            }
            if (! $locked->items()->exists()) {
                throw new InvalidInvoice('Add at least one line before issuing.');
            }

            ($this->recalculate)($locked);

            $issueDate = $locked->issue_date ?? now()->startOfDay();
            $year = (int) $issueDate->format('Y');
            $next = (int) Invoice::query()->where('sequence_year', $year)->lockForUpdate()->max('sequence_no') + 1;

            $client = $locked->client;
            $taxId = $client->vat_number;

            $locked->forceFill([
                'sequence_year' => $year,
                'sequence_no' => $next,
                'number' => sprintf('%s-%d-%04d', strtoupper($this->invoicing->prefix), $year, $next),
                'issue_date' => $issueDate,
                'due_date' => $locked->due_date ?? $issueDate->copy()->addDays($this->invoicing->default_due_days),
                'status' => InvoiceStatus::Sent,
                'issued_at' => now(),
                'reverse_charge' => TaxRules::isReverseCharge($client->country_code, $taxId, $locked->tax_rate_bp),
                'tax_note' => TaxRules::note($client->country_code, $taxId, $locked->tax_rate_bp, $this->invoicing->tax_note),
                'client_snapshot' => [
                    'company_name' => $client->company_name,
                    'contact_name' => $client->contact_name,
                    'email' => $client->email,
                    'address_lines' => array_values(array_filter([
                        $client->address_line1,
                        $client->address_line2,
                        trim(implode(', ', array_filter([$client->city, $client->postcode]))),
                        $client->country_code ? (\Locale::getDisplayRegion('-'.$client->country_code, 'en') ?: $client->country_code) : null,
                    ])),
                    'country_code' => $client->country_code,
                    'tax_id' => $taxId,
                    'tax_id_label' => TaxRules::clientTaxIdLabel($client->country_code),
                ],
                'seller_snapshot' => [
                    'name' => $this->profile->name,
                    'title' => $this->profile->title,
                    'business_name' => $this->payment->business_name,
                    'address' => $this->payment->business_address,
                    'email' => $this->payment->business_email ?: ($this->profile->email ?: 'hello@chinmoybiswas.com'),
                    'tax_id' => $this->payment->business_tax_id,
                    'bank_details' => $this->payment->bank_details,
                    'wise_details' => $this->payment->wise_details,
                    'paypal_email' => $this->payment->paypal_email,
                    'footer' => $this->invoicing->footer,
                ],
            ])->save();
        });

        return ($this->generatePdf)($invoice->refresh());
    }
}
