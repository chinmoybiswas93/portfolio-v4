<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Tables;

use App\Enums\TagType;
use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('type')->badge(),
                TextColumn::make('slug')->color('gray'),
            ])
            ->defaultSort('name')
            ->filters([SelectFilter::make('type')->options(TagType::class)])
            ->recordActions(StandardTable::rowActions([EditAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
