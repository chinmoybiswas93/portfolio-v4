<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use App\Filament\Support\Fields;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                ...Fields::titleAndSlug('name', 'blog/tag'),
            ]),
        ]);
    }
}
