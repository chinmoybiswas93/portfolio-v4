<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Invoicing\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\InvoicePdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-only inline view of one PDF version (Preview PDF and the email log). Registered inside the Filament
 * panel's authenticated routes, so it needs the admin login and 2FA like every other admin screen.
 */
final class InvoicePdfController
{
    /** Ids are resolved here, not by route binding, so the panel's login and 2FA checks always run first. */
    public function __invoke(int $invoice, int $pdf): StreamedResponse
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        Gate::authorize('view', $invoice);
        $pdf = InvoicePdf::query()->whereKey($pdf)->where('invoice_id', $invoice->getKey())->firstOrFail();

        $disk = Storage::disk(GenerateInvoicePdf::DISK);
        abort_unless($disk->exists($pdf->path), 404);

        return $disk->response($pdf->path, $invoice->number.'-'.$pdf->label().'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            // Shown in the admin's own preview iframe; other sites still can't frame it.
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ], request()->boolean('download') ? 'attachment' : 'inline');
    }
}
