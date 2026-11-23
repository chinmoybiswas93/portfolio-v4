<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Invoicing\MarkOverdueInvoices;
use Illuminate\Console\Command;

final class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent invoices that are past their due date as overdue';

    public function handle(MarkOverdueInvoices $markOverdue): int
    {
        $this->info($markOverdue().' invoice(s) marked overdue.');

        return self::SUCCESS;
    }
}
