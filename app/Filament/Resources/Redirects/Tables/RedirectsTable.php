<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Tables;

use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('From')->searchable()->weight('medium'),
                TextColumn::make('to_path')->label('To')->searchable(),
                TextColumn::make('status_code')->label('Type')->badge(),
                TextColumn::make('hits')->numeric()->sortable(),
            ])
            ->defaultSort('from_path')
            ->recordActions(StandardTable::rowActions([EditAction::make()->modalWidth(Width::Large), DeleteAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
