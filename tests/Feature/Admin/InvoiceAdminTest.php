<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Filament\Pages\ManagePayment;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Widgets\OutstandingByCurrency;
use App\Filament\Widgets\OverdueInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Settings\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->actingAs(adminWithMfa());
});

it('creates a client with a country-specific tax ID label', function (): void {
    Livewire::test(ManageClients::class)
        ->mountAction('create')
        ->fillForm(['company_name' => 'Acme GmbH', 'email' => 'billing@acme.test', 'default_currency' => 'EUR', 'country_code' => 'DE'])
        ->fillForm(['vat_number' => 'DE123456789'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Client::query()->where('company_name', 'Acme GmbH')->value('vat_number'))->toBe('DE123456789');
});

it('creates a draft from decimal input and stores integer amounts', function (): void {
    $client = Client::factory()->create(['default_currency' => 'GBP']);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'client_id' => $client->id,
            'currency' => 'GBP',
            'items' => [['description' => 'Care plan', 'quantity_milli' => '2.5', 'unit_price_minor' => '45.00']],
            'discount_type' => 'percent',
            'discount_value' => '5',
            'tax_rate_bp' => '0',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->with('items')->sole();
    expect($invoice->items->first()?->quantity_milli)->toBe(2500)
        ->and($invoice->items->first()?->unit_price_minor)->toBe(4500)
        ->and($invoice->subtotal_minor)->toBe(11250)
        ->and($invoice->discount_value)->toBe(500)
        ->and($invoice->total_minor)->toBe(10687)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

it('rejects malformed amounts', function (): void {
    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'client_id' => Client::factory()->create()->id,
            'items' => [['description' => 'X', 'quantity_milli' => '1e3', 'unit_price_minor' => '-5']],
        ])
        ->call('create')
        ->assertHasFormErrors(['items.0.quantity_milli' => 'regex', 'items.0.unit_price_minor' => 'regex']);
});

it('issues a draft from the editor', function (): void {
    $invoice = Invoice::factory()->create();
    $invoice->items()->create(['position' => 0, 'description' => 'Work', 'quantity_milli' => 1000, 'unit_price_minor' => 10000]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('issue')
        ->assertNotified();

    expect($invoice->refresh()->number)->toStartWith('INV-')
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->pdf_path)->not->toBeNull();
});

it('refuses to save changes to an issued invoice', function (): void {
    $invoice = Invoice::factory()->create();
    $invoice->items()->create(['position' => 0, 'description' => 'Work', 'quantity_milli' => 1000, 'unit_price_minor' => 10000]);
    app(IssueInvoice::class)($invoice);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->set('data.notes', 'Changed')
        ->call('save');

    expect($invoice->refresh()->notes)->not->toBe('Changed')->and($invoice->total_minor)->toBe(10000);
});

it('only lets drafts be deleted', function (): void {
    $admin = auth()->user();
    $draft = Invoice::factory()->create();
    $issued = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

    expect($admin?->can('delete', $draft))->toBeTrue()
        ->and($admin?->can('delete', $issued))->toBeFalse()
        ->and($admin?->can('deleteAny', Invoice::class))->toBeFalse();
});

it('lists invoices with status tabs', function (): void {
    Invoice::factory()->count(2)->create();

    Livewire::test(ListInvoices::class)->assertOk()->assertCanSeeTableRecords(Invoice::all());
});

it('stores the owner tax ID encrypted', function (): void {
    Livewire::test(ManagePayment::class)->fillForm(['business_tax_id' => 'TIN-998877'])->call('save')->assertHasNoFormErrors();

    expect(app(PaymentSettings::class)->business_tax_id)->toBe('TIN-998877')
        ->and((string) DB::table('settings')->where('group', 'payment')->where('name', 'business_tax_id')->value('payload'))->not->toContain('TIN-998877');
});

it('keeps invoices away from non-admins', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    $this->get(InvoiceResource::getUrl('index'))->assertForbidden();
});

it('shows outstanding totals per currency and overdue invoices on the dashboard', function (): void {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'currency' => 'GBP', 'total_minor' => 100000]);
    Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'currency' => 'GBP', 'total_minor' => 85000, 'number' => 'INV-2026-0012', 'due_date' => now()->subDays(3)]);
    Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'currency' => 'GBP', 'total_minor' => 99999]);

    Livewire::test(OutstandingByCurrency::class)
        ->assertSee('£1,850.00')->assertSee('2 invoices · 1 overdue')->assertSee('Nothing outstanding');
    Livewire::test(OverdueInvoices::class)
        ->assertSee('INV-2026-0012')->assertSee('3 days overdue')->assertSee('£850.00');
});

it('draws status tabs and filters inside the table toolbar and applies them at once', function (): void {
    $gbp = Invoice::factory()->create(['currency' => 'GBP']);
    $eur = Invoice::factory()->create(['currency' => 'EUR', 'status' => InvoiceStatus::Sent]);

    Livewire::test(ListInvoices::class)
        ->assertSeeHtml('class="cb-toolbar"')
        ->assertSeeHtml('wire:model.live="tableFilters.currency.value"')
        ->set('tableFilters.currency.value', 'EUR')
        ->assertCanSeeTableRecords([$eur])->assertCanNotSeeTableRecords([$gbp])
        ->set('tableFilters.currency.value', null)
        ->set('activeTab', 'draft')
        ->assertCanSeeTableRecords([$gbp])->assertCanNotSeeTableRecords([$eur]);
});
