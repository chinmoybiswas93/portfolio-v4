<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is stored as integer minor units with an ISO 4217 currency code; rates in basis points
 * (2000 = 20%); quantities in thousandths (1500 = 1.5). See docs/ARCHITECTURE.md §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email');
            $table->string('phone', 64)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 32)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('vat_number', 64)->nullable();
            $table->char('default_currency', 3)->default('GBP');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('number', 32)->nullable()->unique();
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->unsignedInteger('sequence_no')->nullable();
            $table->string('status', 16)->default('draft');
            $table->char('currency', 3);
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->json('client_snapshot')->nullable();
            $table->json('seller_snapshot')->nullable();
            $table->string('discount_type', 16)->default('none');
            $table->bigInteger('discount_value')->default(0);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->text('notes')->nullable();
            $table->string('tax_note')->nullable();
            $table->string('pdf_path', 512)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['sequence_year', 'sequence_no']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('description');
            $table->bigInteger('quantity_milli');
            $table->bigInteger('unit_price_minor');
            $table->unsignedInteger('discount_bp')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'position']);
        });

        Schema::create('invoice_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_email');
            $table->json('cc')->nullable();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('pdf_path', 512)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_emails');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('clients');
    }
};
