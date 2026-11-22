<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-clients')]
#[Description('List invoice clients (company, contact, email, country, default currency). Read only.')]
final class ListClients extends ScopedTool
{
    public static function scope(): string
    {
        return 'invoices:read';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->string()->description('Part of the company name')];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $clients = Client::query()
            ->when(filled($data['search'] ?? null), fn ($q) => $q->where('company_name', 'like', '%'.addcslashes((string) $data['search'], '%_\\').'%'))
            ->orderBy('company_name')->limit(100)->get();
        $this->audit('client', null, 'list');

        return Response::json($clients->map(fn (Client $c): array => [
            'id' => $c->id,
            'company_name' => $c->company_name,
            'contact_name' => $c->contact_name,
            'email' => $c->email,
            'country_code' => $c->country_code,
            'default_currency' => $c->default_currency->value,
        ])->values()->all());
    }
}
