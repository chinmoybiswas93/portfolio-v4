<?php

declare(strict_types=1);

namespace App\Filament\Resources\Experiences\Tables;

use App\Filament\Support\StandardTable;
use App\Models\Experience;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExperiencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')->searchable()->weight('medium'),
                TextColumn::make('company')->searchable(),
                TextColumn::make('started_on')->label('Dates')
                    ->formatStateUsing(fn (Experience $record): string => $record->started_on->format('M Y').' – '.($record->ended_on?->format('M Y') ?? 'Now')),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions(StandardTable::rowActions([EditAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
