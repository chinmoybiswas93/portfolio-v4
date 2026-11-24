<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                ...Fields::titleAndSlug('name', 'blog/tag'),
            ]),
            Textarea::make('description')->rows(2)->maxLength(300)->columnSpanFull()
                ->helperText('Optional. Shown in search results for the tag page; one is written for you when empty.'),
        ]);
    }
}
