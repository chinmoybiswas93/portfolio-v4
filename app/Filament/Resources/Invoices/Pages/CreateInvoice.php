<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Invoicing\RecalculateInvoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'New invoice';

    protected function afterCreate(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        app(RecalculateInvoice::class)($invoice);
    }

    protected function getHeaderActions(): array
    {
        return [$this->getCreateFormAction()->formId('form')->label('Save draft')];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
