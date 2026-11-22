<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Invoicing\GenerateInvoicePdf;
use App\Models\InvoicePdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "View invoice" link from the invoice email: a signed URL valid for 30 days that serves exactly one PDF version.
 * No login, no listing; a changed id or an expired/missing signature is rejected by the "signed" middleware.
 */
final class InvoiceLinkController
{
    public const VALID_DAYS = 30;

    public static function signedUrl(InvoicePdf $pdf): string
    {
        return URL::temporarySignedRoute('invoices.link', now()->addDays(self::VALID_DAYS), ['pdf' => $pdf->getKey()]);
    }

    public function __invoke(InvoicePdf $pdf): StreamedResponse
    {
        $disk = Storage::disk(GenerateInvoicePdf::DISK);
        abort_unless($disk->exists($pdf->path), 404);
        $pdf->loadMissing('invoice');

        return $disk->response($pdf->path, $pdf->invoice->number.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ], 'inline');
    }
}
