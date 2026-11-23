<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Filament\Pages\ManageMcp;
use App\Filament\Pages\Mcp\ConnectedApps;
use App\Filament\Pages\Mcp\McpAuditLogPage;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\McpAuditLog;
use App\Settings\McpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['mcp-server.enabled' => true]));

/**
 * @param  array<string, mixed>  $arguments
 */
function invoiceTool(array $arguments, int $status = 200): TestResponse
{
    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'save-invoice-draft', 'arguments' => $arguments]])->assertStatus($status);
}

it('creates and edits a draft invoice with exact integer totals', function (): void {
    Passport::actingAs(adminWithMfa(), ['mcp:use', 'invoices:draft'], 'api');
    $client = Client::factory()->create(['default_currency' => 'GBP']);

    $created = invoiceTool(['client_id' => $client->id, 'lines' => [
        ['description' => 'Plugin fix', 'quantity' => '2.5', 'unit_price' => '40.00'],
        ['description' => 'Hosting set-up', 'quantity' => '1', 'unit_price' => '19.99'],
    ], 'tax_rate' => '20'])->json('result.content.0.text');
    $id = json_decode((string) $created, true)['id'];

    $invoice = Invoice::query()->findOrFail($id);
    expect($invoice->status)->toBe(InvoiceStatus::Draft)->and($invoice->currency->value)->toBe('GBP')
        ->and($invoice->total_minor)->toBe(14399) // (100.00 + 19.99) × 1.20 = 143.988 → 143.99
        ->and($invoice->items()->count())->toBe(2);

    invoiceTool(['id' => $id, 'lines' => [['description' => 'Plugin fix', 'quantity' => '1', 'unit_price' => '40.00']], 'tax_rate' => '0']);
    expect($invoice->refresh()->total_minor)->toBe(4000)->and($invoice->items()->count())->toBe(1);
    expect(McpAuditLog::query()->where('tool', 'save-invoice-draft')->count())->toBe(2);
});

it('refuses to change issued invoices and offers no issue, pay, cancel or send tools', function (): void {
    Passport::actingAs(adminWithMfa(), ['mcp:use', 'invoices:read', 'invoices:draft'], 'api');
    $issued = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'total_minor' => 5000]);

    $text = (string) invoiceTool(['id' => $issued->id, 'notes' => 'changed'])->json('result.content.0.text');
    expect($text)->toContain('Only draft invoices')->and($issued->refresh()->total_minor)->toBe(5000)->and($issued->notes)->not->toBe('changed');

    $names = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'))->pluck('name');
    expect($names->filter(fn (string $n): bool => (bool) preg_match('/issue|pay|cancel|send|email|void/', $n)))->toBeEmpty();
});

it('validates invoice amounts as decimals and needs a real client', function (): void {
    Passport::actingAs(adminWithMfa(), ['mcp:use', 'invoices:draft'], 'api');
    $client = Client::factory()->create();

    expect((string) invoiceTool(['client_id' => 999])->json('result.content.0.text'))->toContain('No client');
    invoiceTool(['client_id' => $client->id, 'lines' => [['description' => 'x', 'quantity' => '1', 'unit_price' => '1e3']]])->assertJsonPath('result.isError', true);
    expect(Invoice::query()->count())->toBe(0);
});

it('needs invoices:draft for drafting', function (): void {
    Passport::actingAs(adminWithMfa(), ['mcp:use', 'invoices:read'], 'api');
    invoiceTool(['client_id' => 1], 403);
});

it('shows the MCP settings tab and saves its switches', function (): void {
    $this->actingAs(adminWithMfa());

    Livewire::test(ManageMcp::class)->assertOk()->assertSee('Server URL')->assertSee('60 calls a minute')->assertSee('content:trash')
        ->fillForm(['enabled' => false, 'registration_open' => '0'])->call('save')->assertHasNoFormErrors();

    expect(app(McpSettings::class)->enabled)->toBeFalse()->and(app(McpSettings::class)->registration_open)->toBeFalse();
});

it('lists connected apps by status and revokes one', function (): void {
    $this->actingAs(adminWithMfa());
    $active = Passport::client()->newQuery()->forceCreate(['name' => 'Claude Code', 'secret' => null, 'provider' => null, 'redirect_uris' => ['http://localhost:5000/cb'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'scopes' => ['mcp:use', 'content:read']]);
    $pending = Passport::client()->newQuery()->forceCreate(['name' => 'Unknown app', 'secret' => null, 'provider' => null, 'redirect_uris' => ['https://claude.ai/cb'], 'grant_types' => ['authorization_code'], 'revoked' => false, 'scopes' => null]);

    Livewire::test(ConnectedApps::class)->assertCanSeeTableRecords([$active, $pending])->assertSee('content:read')->assertSee('Not approved')
        ->set('activeTab', 'active')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$pending])
        ->callTableAction('revoke', $active)->assertNotified();

    expect($active->refresh()->getAttribute('scopes'))->toBeNull();
});

it('shows the audit log with change summaries and exports a formula-safe CSV', function (): void {
    $this->actingAs(adminWithMfa());
    $log = McpAuditLog::query()->create(['method' => 'tools/call', 'client_id' => 'c1', 'client_name' => 'Claude Code', 'tool' => 'save-content', 'scope' => 'content:write', 'target_type' => 'post', 'target_id' => '12', 'target_label' => '=HYPERLINK("http://evil")', 'changes' => ['action' => 'updated', 'fields' => ['title' => ['before' => 'a', 'after' => 'b']]], 'result' => 'ok', 'http_status' => 200, 'ip' => '203.0.113.24']);
    $denied = McpAuditLog::query()->create(['method' => 'tools/call', 'client_id' => 'c1', 'client_name' => 'Claude Code', 'tool' => 'list-invoices', 'result' => 'denied', 'http_status' => 403, 'error' => 'insufficient_scope', 'ip' => '203.0.113.24']);

    Livewire::test(McpAuditLogPage::class)->assertCanSeeTableRecords([$log, $denied])->assertSee('updated: title')
        ->set('activeTab', 'denied')->assertCanSeeTableRecords([$denied])->assertCanNotSeeTableRecords([$log]);

    expect(McpAuditLogPage::csvCell('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")->and(McpAuditLogPage::csvCell('Post #12'))->toBe('Post #12');
});

it('keeps the MCP admin pages behind admin login and 2FA', function (string $path): void {
    $this->get(adminPath('/'.$path))->assertRedirect();
})->with(['mcp/apps', 'mcp/audit', 'settings/mcp']);
