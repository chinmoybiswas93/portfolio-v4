<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Tables;

use App\Filament\Support\StandardTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('slug')->color('gray'),
                TextColumn::make('posts_count')->counts('posts')->label('Posts')->alignEnd(),
                TextColumn::make('projects_count')->counts('projects')->label('Projects')->alignEnd(),
                TextColumn::make('experiences_count')->counts('experiences')->label('Experience')->alignEnd(),
            ])
            ->defaultSort('name')
            ->recordActions(StandardTable::rowActions([EditAction::make()->modalWidth(Width::Large), DeleteAction::make()]))
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
