<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every generated invoice PDF is kept as a numbered version (owner, 2026-11-22); each email links to the exact
 * version it attached. Existing PDFs and the per-email copies are backfilled as versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_pdfs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path', 512);
            $table->char('sha256', 64);
            $table->string('status_at_render', 16);
            $table->timestamps();
            $table->unique(['invoice_id', 'version']);
        });

        Schema::table('invoice_emails', function (Blueprint $table): void {
            $table->foreignId('invoice_pdf_id')->nullable()->after('invoice_id')->constrained('invoice_pdfs')->nullOnDelete();
        });

        $now = now();
        foreach (DB::table('invoices')->whereNotNull('pdf_path')->orderBy('id')->get(['id', 'status', 'pdf_path', 'pdf_sha256', 'pdf_generated_at']) as $invoice) {
            $version = 0;
            foreach (DB::table('invoice_emails')->where('invoice_id', $invoice->id)->whereNotNull('pdf_path')->orderBy('id')->get() as $email) {
                $pdfId = DB::table('invoice_pdfs')->insertGetId([
                    'invoice_id' => $invoice->id, 'version' => ++$version, 'path' => $email->pdf_path,
                    'sha256' => (string) $email->pdf_sha256, 'status_at_render' => (string) $invoice->status,
                    'created_at' => $email->created_at ?? $now, 'updated_at' => $now,
                ]);
                DB::table('invoice_emails')->where('id', $email->id)->update(['invoice_pdf_id' => $pdfId]);
            }
            DB::table('invoice_pdfs')->insert([
                'invoice_id' => $invoice->id, 'version' => ++$version, 'path' => $invoice->pdf_path,
                'sha256' => (string) $invoice->pdf_sha256, 'status_at_render' => (string) $invoice->status,
                'created_at' => $invoice->pdf_generated_at ?? $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoice_emails', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_pdf_id');
        });
        Schema::dropIfExists('invoice_pdfs');
    }
};
