# ADR-0004: Invoice PDF generation and storage

**Status:** Accepted (owner, 2026-11-22)
**Date:** 2026-11-22
**Deciders:** Owner

## Context
- Invoices must be rendered to PDF, stored with a DB record, downloaded, regenerated and emailed.
- The server has no headless Chrome and no `exec` or `popen`, so Browsershot, wkhtmltopdf and Puppeteer are impossible.
- Invoices are legal and financial records. The exact PDF that was emailed must be kept, even if the invoice is regenerated later.

## Decision
- Use **`barryvdh/laravel-dompdf`** (pure PHP, CPDF backend) and render the Blade view `pdf.invoice` (design approved in Phase 2).
- **Template rules** (DomPDF supports about CSS 2.1): table-based layout, inline `<style>`, no flex or grid, A4 portrait. Embed a self-hosted TTF font with £/€/$ glyphs. Load the logo as a **data URI**. Keep `enable_remote=false`, `enable_php=false`, and `chroot=base_path()`. Use `font_dir` and `font_cache` = `storage/fonts` (in `shared/`, writable).
- **Storage:** private local disk `invoices` → `storage/app/private/invoices/{yyyy}/{number}.pdf`. It is **never** web-served directly. Downloads go through an authorised Filament action or route (`InvoicePolicy@view`) with `Content-Disposition: attachment`.
- **DB record:** `invoices.pdf_path`, `pdf_generated_at` and `pdf_sha256`. "Regenerate" overwrites the working copy.
- **Immutable sent copies:** when an invoice is emailed, the attached PDF is copied to `invoices/{yyyy}/sent/{number}-{invoice_email_id}.pdf`, and that path and hash are stored on `invoice_emails`. This gives a full audit trail of exactly what each client received.
- Generation is **synchronous** from admin. One A4 invoice takes well under 1 second. A `GenerateInvoicePdf` Action is used by both the Filament action and the email action.

## Options considered
| Option | Verdict |
|---|---|
| **DomPDF via laravel-dompdf** | **Chosen.** Pure PHP, mature, enough for a tabular invoice. |
| spatie/laravel-pdf with the DomPDF driver | Same engine with an extra abstraction. Could be adopted later if the external Gotenberg or Cloudflare driver is ever wanted. |
| Browsershot / Puppeteer | Impossible: needs Chrome and `exec`. |
| External API (Gotenberg, Cloudflare Browser Rendering, DocRaptor) | Better CSS, but a paid or extra service, a network dependency, and invoice data leaves the server. Rejected. |
| mPDF / TCPDF | Viable. DomPDF is chosen for its better HTML/CSS ergonomics and Laravel wrapper. |

## Consequences
- Easier: no external dependency, deterministic output that is easy to test (assert the PDF is generated, the hash is stored and the text extracts).
- Harder: the template must avoid modern CSS. The Phase 2 design must respect DomPDF limits.
- Backups must include `shared/storage/app/private/invoices` (Hostinger backups + the Phase 5 runbook).
