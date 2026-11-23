<?php

declare(strict_types=1);

namespace App\Filament\Resources\Experiences\Schemas;

use App\Filament\Forms\MarkdownField;
use App\Filament\Support\Fields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExperienceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('position')->required()->maxLength(255),
                TextInput::make('company')->required()->maxLength(255),
                TextInput::make('company_url')->label('Company website')->url()->maxLength(512),
                TextInput::make('location')->maxLength(255),
                DatePicker::make('started_on')->required()->native(false)->displayFormat('M Y'),
                DatePicker::make('ended_on')->native(false)->displayFormat('M Y')->afterOrEqual('started_on')
                    ->helperText('Leave empty for your current role.'),
                MarkdownField::make('description_md')->label('Description')->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'link', 'bulletList'], ['undo', 'redo']]),
                Fields::tags('Skills')->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                Toggle::make('is_published')->label('Published')->default(true),
            ]),
        ]);
    }
}
