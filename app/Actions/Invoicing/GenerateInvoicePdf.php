<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\InvoiceDocument;
use App\Enums\InvoiceAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoicePdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the A4 PDF (DomPDF, ADR-0004) to the private disk as a new numbered version (never overwritten).
 */
final class GenerateInvoicePdf
{
    public const DISK = 'local';

    public function __invoke(Invoice $invoice): Invoice
    {
        if (! $invoice->status->allows(InvoiceAction::GeneratePdf) || $invoice->number === null) {
            throw new InvalidInvoice($invoice->status === InvoiceStatus::Draft
                ? 'Issue the invoice before generating its PDF.'
                : 'A '.strtolower($invoice->status->getLabel()).' invoice keeps the PDF it has.');
        }

        $pdf = $this->render($invoice);

        // Every version is kept: invoices/{year}/{number}-v{n}.pdf is written once and never overwritten.
        DB::transaction(function () use ($invoice, $pdf): void {
            $version = (int) InvoicePdf::query()->where('invoice_id', $invoice->getKey())->lockForUpdate()->max('version') + 1;
            $path = sprintf('invoices/%d/%s-v%d.pdf', $invoice->sequence_year, $invoice->number, $version);
            $disk = Storage::disk(self::DISK);
            if ($disk->exists($path)) {
                throw new InvalidInvoice('A PDF version with this number already exists.');
            }
            $disk->put($path, $pdf);
            $sha = hash('sha256', $pdf);

            InvoicePdf::query()->create([
                'invoice_id' => $invoice->getKey(),
                'version' => $version,
                'path' => $path,
                'sha256' => $sha,
                'status_at_render' => $invoice->status,
            ]);

            // The invoice keeps pointing at its newest version.
            $invoice->forceFill(['pdf_path' => $path, 'pdf_sha256' => $sha, 'pdf_generated_at' => now()])->save();
        });

        return $invoice;
    }

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('items');
        // DomPDF writes font metrics for the bundled Inter files here on first use.
        File::ensureDirectoryExists(storage_path('fonts'));
        $logo = (string) file_get_contents(public_path('brand/logo-mark.svg'));

        $dompdf = Pdf::loadView('pdf.invoice', [
            'doc' => InvoiceDocument::from($invoice),
            'logo' => 'data:image/svg+xml;base64,'.base64_encode($logo),
        ])->setPaper('a4')->getDomPDF();
        $dompdf->render();

        // CSS counter(pages) is not supported, so the "Page x / y" footer is drawn on every page here.
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = (string) ($metrics->getFont('Inter') ?? $metrics->getFont('DejaVu Sans'));
        $size = 6;
        $width = $metrics->getTextWidth('Page 9 / 9', $font, $size);
        $canvas->page_text($canvas->get_width() - 33 - $width, $canvas->get_height() - 28, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, $size, [0.357, 0.42, 0.502]);

        return (string) $dompdf->output();
    }
}
