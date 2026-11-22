<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

/**
 * Admin only. Issued invoices are never deleted: numbering stays gap-free (cancel them instead).
 */
final class InvoicePolicy extends AdminOnlyPolicy
{
    public function delete(User $user, ?Invoice $invoice = null): bool
    {
        return parent::delete($user) && $invoice?->status === InvoiceStatus::Draft;
    }

    public function forceDelete(User $user, ?Invoice $invoice = null): bool
    {
        return $this->delete($user, $invoice);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
