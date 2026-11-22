<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Everything the admin can do to an invoice. InvoiceStatus::allows() is the single source of truth for which
 * of these each status offers; buttons and server-side actions both check it (owner, 2026-11-22).
 */
enum InvoiceAction: string
{
    case Edit = 'edit';
    case Issue = 'issue';
    case GeneratePdf = 'generate_pdf';
    case Download = 'download';
    case Email = 'email';
    case MarkPaid = 'mark_paid';
    case Cancel = 'cancel';
    case Duplicate = 'duplicate';
    case Delete = 'delete';
}
