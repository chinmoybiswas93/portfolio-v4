<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\PublishStatus;
use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')->collection('cover')->conversion('thumb')->label('')->width(80),
                TextColumn::make('title')->searchable()->sortable()->weight('medium'),
                TextColumn::make('year')->sortable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
                IconColumn::make('has_case_study')->label('Case study')->boolean(),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions(StandardTable::rowActions([
                EditAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
