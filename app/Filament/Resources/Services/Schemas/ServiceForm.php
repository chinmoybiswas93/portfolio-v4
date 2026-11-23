<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Forms\MarkdownField;
use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                ...Fields::titleAndSlug(),
                Textarea::make('summary')->rows(3)->maxLength(500)->columnSpanFull()
                    ->helperText('Shown on the home page under the service title.'),
                MarkdownField::make('body_md')->label('Details (optional)')->columnSpanFull(),
                TextInput::make('icon')->maxLength(64)->helperText('Optional icon name.'),
                TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                Toggle::make('is_published')->label('Published')->default(true),
            ]),
        ]);
    }
}
