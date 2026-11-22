<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OverdueInvoices extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Overdue invoices')
            ->query(Invoice::query()->with('client')->where('status', InvoiceStatus::Overdue)->orderBy('due_date'))
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('client.company_name')->label('Client'),
                TextColumn::make('due_date')->label('Due')->badge()->color('danger')
                    ->formatStateUsing(fn (Invoice $record): string => (int) $record->due_date?->startOfDay()->diffInDays(now()->startOfDay()).' days overdue'),
                TextColumn::make('total_minor')->label('Total')->alignEnd()
                    ->formatStateUsing(fn (Invoice $record): string => Money::format($record->total_minor, $record->currency)),
            ])
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]))
            ->headerActions([Action::make('all')->label('View all')->link()->url(InvoiceResource::getUrl('index', ['tab' => 'overdue']))])
            ->emptyStateHeading('No overdue invoices')
            ->paginated(false);
    }
}
