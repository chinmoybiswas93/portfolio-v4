<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Tables;

use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium'),
                TextColumn::make('summary')->limit(80)->color('gray'),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([TrashedFilter::make()])
            ->recordActions(StandardTable::rowActions([EditAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
