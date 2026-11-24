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
                ...Fields::titleAndSlug(urlPrefix: 'services'),
                Textarea::make('summary')->rows(3)->maxLength(500)->columnSpanFull()
                    ->helperText('Shown on the home page, the services list and as the page lead.'),
                MarkdownField::make('body_md')->label('Page details')->columnSpanFull()
                    ->helperText('Who it is for, what you get, how you work. A published service gets its own page once this is filled in.'),
                TextInput::make('icon')->maxLength(64)->helperText('Optional icon name.'),
                TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                Toggle::make('is_published')->label('Published')->default(true),
            ]),
            // Landing page (ADR-0010, slice 8d).
            Section::make('Related work and FAQ')->compact()->columnSpanFull()->schema([
                Fields::tags('Topics')
                    ->helperText('Case studies and posts with these tags are listed as Related work.'),
                Fields::faq(),
            ]),
            Section::make('Contact and SEO')->compact()->columns(2)->columnSpanFull()->schema([
                TextInput::make('cta_line')->label('Contact box heading')->maxLength(160)->columnSpanFull()
                    ->placeholder('Have a project in mind?'),
                Fields::seoTitle(),
                Fields::seoDescription(),
            ]),
        ]);
    }
}
