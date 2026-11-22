<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\InvoiceDocument;
use App\Enums\InvoiceAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the A4 PDF (DomPDF, ADR-0004) to the private disk: invoices/{year}/{number}.pdf. Regenerating
 * overwrites this working copy; copies attached to emails are kept separately and never change.
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
        $path = sprintf('invoices/%d/%s.pdf', $invoice->sequence_year, $invoice->number);
        Storage::disk(self::DISK)->put($path, $pdf);

        $invoice->forceFill([
            'pdf_path' => $path,
            'pdf_sha256' => hash('sha256', $pdf),
            'pdf_generated_at' => now(),
        ])->save();

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
