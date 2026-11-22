<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostStatus;
use App\Filament\Support\StandardTable;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->weight('medium')->limit(70),
                TextColumn::make('category.name')->label('Category')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('reading_minutes')->label('Read')->suffix(' min'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(PostStatus::class),
                SelectFilter::make('category_id')->label('Category')->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions(StandardTable::rowActions([EditAction::make()]))
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make(), ForceDeleteBulkAction::make(), RestoreBulkAction::make()]),
            ]);
    }
}
