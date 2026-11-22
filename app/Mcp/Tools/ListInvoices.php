<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-invoices')]
#[Description('List invoices, newest first, optionally by status. Amounts are integer minor units plus a formatted string.')]
final class ListInvoices extends ScopedTool
{
    public static function scope(): string
    {
        return 'invoices:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(InvoiceStatus::cases(), 'value')),
            'page' => $schema->integer()->min(1)->default(1),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(InvoiceStatus::cases(), 'value'))],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $page = Invoice::query()->with('client')
            ->when(filled($data['status'] ?? null), fn ($q) => $q->where('status', $data['status']))
            ->latest()->paginate(25, ['*'], 'page', (int) ($data['page'] ?? 1));

        $this->audit('invoice', null, 'list');

        return Response::json([
            'items' => collect($page->items())->map(fn (Invoice $i): array => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status->value,
                'client' => $i->client?->company_name,
                'currency' => $i->currency->value,
                'total_minor' => $i->total_minor,
                'total' => Money::format($i->total_minor, $i->currency),
                'issue_date' => $i->issue_date?->toDateString(),
                'due_date' => $i->due_date?->toDateString(),
            ])->values()->all(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }
}
