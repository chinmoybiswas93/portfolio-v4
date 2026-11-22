<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Company')->searchable()->sortable()->weight('medium'),
                TextColumn::make('contact_name')->searchable()->toggleable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('country_code')->label('Country')->badge()->color('gray'),
                TextColumn::make('default_currency')->label('Currency'),
                TextColumn::make('invoices_count')->counts('invoices')->label('Invoices'),
            ])
            ->defaultSort('company_name')
            ->filters([TrashedFilter::make()])
            ->recordActions(StandardTable::rowActions([EditAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), ForceDeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
