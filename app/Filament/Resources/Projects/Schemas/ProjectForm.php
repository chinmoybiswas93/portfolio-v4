<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\PublishStatus;
use App\Enums\TagType;
use App\Filament\Support\Fields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    private const MARKDOWN_TOOLBAR = [
        ['bold', 'italic', 'link'],
        ['heading', 'blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['undo', 'redo'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Project')->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Basics')->schema([
                    Grid::make(2)->schema([
                        ...Fields::titleAndSlug(),
                        Textarea::make('summary')->rows(3)->maxLength(500)->columnSpanFull()
                            ->helperText('One or two sentences, shown on the home page and in the archive.'),
                        TextInput::make('client_name')->maxLength(255),
                        TextInput::make('sector')->maxLength(255),
                        TextInput::make('role')->maxLength(255),
                        TextInput::make('made_at')->label('Made at')->maxLength(255)->placeholder('Personal'),
                        TextInput::make('year')->numeric()->minValue(2000)->maxValue(2100),
                        Grid::make(2)->schema([
                            DatePicker::make('started_on')->native(false)->displayFormat('M Y'),
                            DatePicker::make('ended_on')->native(false)->displayFormat('M Y')->afterOrEqual('started_on'),
                        ]),
                    ]),
                ]),
                Tab::make('Case study')->schema([
                    Toggle::make('has_case_study')->label('Has a case study page')->live()
                        ->helperText('Off: the project links to its live site instead of a case study page.'),
                    MarkdownEditor::make('problem_md')->label('Problem')->toolbarButtons(self::MARKDOWN_TOOLBAR)
                        ->visible(fn (Get $get): bool => (bool) $get('has_case_study')),
                    MarkdownEditor::make('approach_md')->label('Approach')->toolbarButtons(self::MARKDOWN_TOOLBAR)
                        ->visible(fn (Get $get): bool => (bool) $get('has_case_study')),
                    MarkdownEditor::make('results_md')->label('Results')->toolbarButtons(self::MARKDOWN_TOOLBAR)
                        ->visible(fn (Get $get): bool => (bool) $get('has_case_study')),
                    Repeater::make('metrics')->schema([
                        TextInput::make('label')->required()->maxLength(60),
                        TextInput::make('value')->required()->maxLength(30),
                    ])->columns(2)->reorderable()->maxItems(6)->defaultItems(0)
                        ->visible(fn (Get $get): bool => (bool) $get('has_case_study'))
                        ->helperText('Only real, verifiable numbers.'),
                ]),
                Tab::make('Media')->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->collection('cover')
                        ->image()
                        ->imageEditor()
                        ->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']),
                    TextInput::make('cover_alt')
                        ->label('Cover alt text')
                        ->maxLength(200)
                        ->dehydrated(false)
                        ->requiredWith('cover')
                        ->helperText('Describe the image for screen readers, e.g. "TuFlamenco home page".'),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->maxFiles(12)
                        ->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                        ->customProperties(fn (Get $get): array => ['alt' => trim($get('title').' screenshot')]),
                ]),
                Tab::make('Stack & links')->schema([
                    Fields::tags(TagType::Tech, 'Tech stack'),
                    Grid::make(2)->schema([
                        TextInput::make('live_url')->label('Live site')->url()->maxLength(512),
                        TextInput::make('repo_url')->label('Repository')->url()->maxLength(512),
                    ]),
                ]),
                Tab::make('SEO')->schema([
                    Fields::seoTitle(),
                    Fields::seoDescription(),
                ]),
                Tab::make('Publishing')->schema([
                    Section::make()->columns(2)->schema([
                        Select::make('status')->options(PublishStatus::class)->default(PublishStatus::Draft)->required(),
                        DateTimePicker::make('published_at')->native(false)
                            ->helperText('Set automatically when first published.'),
                        Toggle::make('is_featured')->label('Featured on the home page'),
                        TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                    ]),
                ]),
            ]),
        ]);
    }
}
