<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-11-22: no per-line tax or discount. Lines carry one amount; VAT is a single rate on the
 * invoice total after the invoice-level discount. No invoices existed when this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_bp', 'tax_rate_bp', 'net_minor', 'discount_minor', 'tax_minor', 'total_minor']);
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->bigInteger('amount_minor')->default(0)->after('unit_price_minor');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('reference')->nullable()->after('currency');
            $table->unsignedInteger('tax_rate_bp')->default(0)->after('discount_value');
            $table->boolean('reverse_charge')->default(false)->after('tax_note');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['reference', 'tax_rate_bp', 'reverse_charge']);
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('amount_minor');
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unsignedInteger('discount_bp')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
        });
    }
};
