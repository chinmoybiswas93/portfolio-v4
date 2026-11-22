<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Invoicing\RecalculateInvoice;
use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\InvoiceAction;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Settings\InvoiceSettings;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-invoice-draft')]
#[Description('Create (no id) or edit (with id) a DRAFT invoice for an existing client: currency, reference, dates, lines (description, quantity, unit price as decimals), discount and VAT rate. Issued invoices cannot be changed, and issuing, marking paid, cancelling and emailing are only possible in the admin. Amounts are decimals like "45.00"; quantities like "2.5".')]
final class SaveInvoiceDraft extends ScopedTool
{
    public static function scope(): string
    {
        return 'invoices:draft';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Draft invoice id; omit to create'),
            'client_id' => $schema->integer()->description('Required when creating (see list-clients)'),
            'currency' => $schema->string()->enum(array_column(Currency::cases(), 'value')),
            'reference' => $schema->string(),
            'issue_date' => $schema->string()->description('YYYY-MM-DD'),
            'due_date' => $schema->string()->description('YYYY-MM-DD'),
            'lines' => $schema->array()->description('Replaces all lines: [{"description": "…", "quantity": "1", "unit_price": "45.00"}]'),
            'discount_type' => $schema->string()->enum(['none', 'percent', 'fixed']),
            'discount_value' => $schema->string()->description('Percent ("5") or amount ("20.00")'),
            'tax_rate' => $schema->string()->description('VAT percent on the total, e.g. "0" or "20"'),
            'notes' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $decimal2 = 'regex:/^\d{1,11}(\.\d{1,2})?$/';
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'client_id' => ['required_without:id', 'nullable', 'integer'],
            'currency' => ['nullable', 'in:'.implode(',', array_column(Currency::cases(), 'value'))],
            'reference' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'lines' => ['sometimes', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'lines.*.unit_price' => ['required', $decimal2],
            'discount_type' => ['nullable', 'in:none,percent,fixed'],
            'discount_value' => ['nullable', $decimal2],
            'tax_rate' => ['nullable', $decimal2],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        if (! $user instanceof User) {
            return Response::error('Not allowed.');
        }

        $creating = ! isset($data['id']);
        $invoice = $creating ? new Invoice : Invoice::query()->find($data['id']);
        if ($invoice === null) {
            return Response::error('No invoice with that id.');
        }
        if (! $creating && ! $invoice->status->allows(InvoiceAction::Edit)) {
            return Response::error('Only draft invoices can be edited here. This one is '.strtolower($invoice->status->getLabel()).'.');
        }
        if (Gate::forUser($user)->denies($creating ? 'create' : 'update', $creating ? Invoice::class : $invoice)) {
            return Response::error('Not allowed.');
        }
        if (isset($data['client_id']) && ! Client::query()->whereKey($data['client_id'])->exists()) {
            return Response::error('No client with that id. Clients are added in the admin.');
        }

        try {
            DB::transaction(function () use ($invoice, $data, $creating): void {
                $settings = app(InvoiceSettings::class);
                $fill = array_filter([
                    'client_id' => $data['client_id'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'issue_date' => $data['issue_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ], fn (mixed $v): bool => $v !== null);
                if ($creating) {
                    $client = Client::query()->findOrFail($data['client_id']);
                    $fill += ['currency' => $client->default_currency->value, 'tax_rate_bp' => $settings->default_tax_rate_bp];
                }
                if (isset($data['discount_type'])) {
                    $fill['discount_type'] = DiscountType::from($data['discount_type']);
                    $fill['discount_value'] = Money::parseDecimal($data['discount_value'] ?? '0', 2) ?? 0;
                }
                if (isset($data['tax_rate'])) {
                    $fill['tax_rate_bp'] = Money::parseDecimal($data['tax_rate'], 2) ?? 0;
                }
                $invoice->fill($fill);
                $invoice->save();

                if (isset($data['lines'])) {
                    $invoice->items()->delete();
                    foreach (array_values($data['lines']) as $i => $line) {
                        $invoice->items()->create([
                            'position' => $i,
                            'description' => (string) $line['description'],
                            'quantity_milli' => Money::parseDecimal((string) $line['quantity'], 3) ?? 0,
                            'unit_price_minor' => Money::parseDecimal((string) $line['unit_price'], 2) ?? 0,
                        ]);
                    }
                }
                app(RecalculateInvoice::class)($invoice);
            });
        } catch (InvalidInvoice $e) {
            return Response::error('Not saved: '.$e->getMessage());
        }

        $invoice->refresh();
        $this->audit('invoice', $invoice->getKey(), 'Draft for '.$invoice->client?->company_name, ['action' => $creating ? 'draft created' : 'draft updated', 'total' => Money::format($invoice->total_minor, $invoice->currency)]);

        return Response::json([
            'id' => $invoice->id,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency->value,
            'total' => Money::format($invoice->total_minor, $invoice->currency),
            'total_minor' => $invoice->total_minor,
            'note' => 'Saved as a draft. Review and issue it in the admin.',
        ]);
    }
}
