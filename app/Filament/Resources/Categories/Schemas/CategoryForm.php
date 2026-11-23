<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                ...Fields::titleAndSlug('name', 'blog/category'),
                Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),
        ]);
    }
}
