<?php

declare(strict_types=1);

namespace App\Domain\Invoicing;

use App\Enums\DiscountType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Money;

/**
 * Everything the PDF and email need, as display strings, built from the invoice and its frozen snapshots.
 */
final readonly class InvoiceDocument
{
    /**
     * @param  array<string, mixed>  $seller
     * @param  array<string, mixed>  $client
     * @param  list<array{title: string, note: string, qty: string, price: string, amount: string}>  $lines
     * @param  list<array{label: string, value: string}>  $totals
     * @param  list<array{label: string, text: string}>  $payment
     */
    public function __construct(
        public string $number,
        public string $status,
        public string $issueDate,
        public string $dueDate,
        public string $currency,
        public ?string $reference,
        public array $seller,
        public array $client,
        public array $lines,
        public array $totals,
        public string $totalDue,
        public ?string $notes,
        public array $payment,
        public string $footer,
    ) {}

    public static function from(Invoice $invoice): self
    {
        $currency = $invoice->currency;
        $seller = $invoice->seller_snapshot ?? [];
        $client = $invoice->client_snapshot ?? [];

        $lines = $invoice->items->map(function (InvoiceItem $item) use ($currency): array {
            $parts = preg_split('/\R/', trim($item->description), 2) ?: [''];

            return [
                'title' => $parts[0],
                'note' => trim($parts[1] ?? ''),
                'qty' => Money::toDecimalString($item->quantity_milli, 3, trim: true),
                'price' => Money::format($item->unit_price_minor, $currency, forPdf: true),
                'amount' => Money::format($item->amount_minor, $currency, forPdf: true),
            ];
        })->values()->all();

        $totals = [['label' => 'Subtotal', 'value' => Money::format($invoice->subtotal_minor, $currency, forPdf: true)]];
        if ($invoice->discount_minor > 0) {
            $label = $invoice->discount_type === DiscountType::Percent
                ? 'Discount ('.Money::toDecimalString($invoice->discount_value, 2, trim: true).'%)'
                : 'Discount';
            $totals[] = ['label' => $label, 'value' => Money::format(-$invoice->discount_minor, $currency, forPdf: true)];
        }
        $totals[] = [
            'label' => 'VAT ('.Money::toDecimalString($invoice->tax_rate_bp, 2, trim: true).'%)'.($invoice->discount_minor > 0 ? ' on total after discount' : ''),
            'value' => Money::format($invoice->tax_minor, $currency, forPdf: true),
        ];

        $notes = collect([$invoice->tax_note, $invoice->notes])->filter(fn (?string $n): bool => trim((string) $n) !== '')->implode("\n\n");

        $payment = collect([
            ['label' => 'Bank transfer', 'text' => (string) ($seller['bank_details'] ?? '')],
            ['label' => 'Wise', 'text' => (string) ($seller['wise_details'] ?? '')],
            ['label' => 'PayPal', 'text' => (string) ($seller['paypal_email'] ?? '')],
        ])->filter(fn (array $p): bool => trim($p['text']) !== '')->values()->all();

        $dueDays = $invoice->issue_date && $invoice->due_date ? (int) $invoice->issue_date->diffInDays($invoice->due_date) : null;

        return new self(
            number: $invoice->number ?? 'DRAFT',
            status: $invoice->status->getLabel(),
            issueDate: $invoice->issue_date?->format('j M Y') ?? '—',
            dueDate: $invoice->due_date?->format('j M Y') ?? '—',
            currency: $currency->value,
            reference: $invoice->reference,
            seller: $seller,
            client: $client,
            lines: $lines,
            totals: $totals,
            totalDue: Money::format($invoice->total_minor, $currency, forPdf: true),
            notes: $notes !== '' ? $notes : null,
            payment: $payment,
            footer: trim((string) ($seller['footer'] ?? '')) !== ''
                ? (string) $seller['footer']
                : 'Thank you for your business.'.($dueDays !== null ? " Payment is due within {$dueDays} days." : ''),
        );
    }
}
