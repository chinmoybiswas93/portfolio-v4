<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use App\Enums\TagType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(64),
                TextInput::make('slug')->required()->alphaDash()->maxLength(64)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('type', $get('type'))),
                Select::make('type')->options(TagType::class)->default(TagType::Topic)->required()->live()
                    ->helperText('Topic: blog tags. Tech: project and experience stack.'),
            ]),
        ]);
    }
}
