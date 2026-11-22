<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Enums\DiscountType;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Admin invoice screens in a real browser (signed in as a test admin, so no TOTP is needed). Screenshots go to
 * tests/Browser/Screenshots (git-ignored) when SCREENSHOTS=1.
 */
beforeEach(function (): void {
    $this->actingAs(adminWithMfa());
    $client = Client::factory()->create(['company_name' => 'Acme Ltd', 'country_code' => 'GB', 'vat_number' => 'GB123456789', 'default_currency' => 'GBP']);
    $this->draft = Invoice::factory()->for($client)->create(['discount_type' => DiscountType::Percent, 'discount_value' => 500, 'reference' => 'Website care plan — November 2026']);
    foreach ([[1000, 35000, 'Website care plan — November 2026'], [6000, 4500, 'Custom Gutenberg block'], [2500, 4500, 'Plugin updates']] as $i => [$q, $p, $d]) {
        $this->draft->items()->create(['position' => $i, 'description' => $d, 'quantity_milli' => $q, 'unit_price_minor' => $p]);
    }
    app(RecalculateInvoice::class)($this->draft);
});

it('shows the invoice list and the draft editor with live totals', function (): void {
    $list = visit(adminPath('/invoices'))->assertSee('Invoices')->assertSee('Acme Ltd')->assertNoJavaScriptErrors();
    if (env('SCREENSHOTS')) {
        $list->screenshot(filename: 'invoices-list');
    }

    $edit = visit(adminPath("/invoices/{$this->draft->id}/edit"))
        ->assertSee('Draft invoice')->assertSee('Assigned on issue')->assertSee('£695.87')->assertSee('Issue invoice')
        ->assertNoJavaScriptErrors();
    if (env('SCREENSHOTS')) {
        $edit->screenshot(fullPage: true, filename: 'invoice-draft');
    }
});

it('shows an issued invoice as locked with its actions', function (): void {
    app(IssueInvoice::class)($this->draft);

    $page = visit(adminPath("/invoices/{$this->draft->id}/edit"))
        ->assertSee($this->draft->refresh()->number)->assertSee('Email to client')->assertSee('Download')->assertSee('Mark as paid')
        ->assertDontSee('Issue invoice')->assertNoJavaScriptErrors();
    if (env('SCREENSHOTS')) {
        $page->screenshot(fullPage: true, filename: 'invoice-issued');
    }
});
