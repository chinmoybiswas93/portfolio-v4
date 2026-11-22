<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-invoice')]
#[Description('Get one invoice by id or number with its lines, totals and client. Read only.')]
final class GetInvoice extends ScopedTool
{
    public static function scope(): string
    {
        return 'invoices:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer(),
            'number' => $schema->string()->description('e.g. INV-2026-0012'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'required_without:number'],
            'number' => ['nullable', 'string', 'max:40', 'required_without:id'],
        ]);
        $query = Invoice::query()->with(['items', 'client']);
        $invoice = isset($data['id']) ? $query->whereKey((int) $data['id'])->first() : $query->where('number', $data['number'])->first();
        if ($invoice === null) {
            return Response::error('No invoice found.');
        }
        $this->audit('invoice', $invoice->id, $invoice->number ?? 'Draft');
        $money = fn (int $minor): string => Money::format($minor, $invoice->currency);

        return Response::json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'editable' => ! $invoice->status->isLocked(),
            'currency' => $invoice->currency->value,
            'reference' => $invoice->reference,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'client' => $invoice->client ? ['id' => $invoice->client->id, 'company_name' => $invoice->client->company_name] : null,
            'lines' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'description' => $item->description,
                'quantity' => Money::toDecimalString($item->quantity_milli, 3, trim: true),
                'unit_price_minor' => $item->unit_price_minor,
                'amount_minor' => $item->amount_minor,
            ])->values()->all(),
            'discount_type' => $invoice->discount_type->value,
            'discount_value' => $invoice->discount_value,
            'tax_rate_bp' => $invoice->tax_rate_bp,
            'totals' => [
                'subtotal' => $money($invoice->subtotal_minor),
                'discount' => $money($invoice->discount_minor),
                'tax' => $money($invoice->tax_minor),
                'total' => $money($invoice->total_minor),
                'total_minor' => $invoice->total_minor,
            ],
            'notes' => $invoice->notes,
        ]);
    }
}
