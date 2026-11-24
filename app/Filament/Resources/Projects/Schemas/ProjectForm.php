<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\PublishStatus;
use App\Filament\Forms\MarkdownField;
use App\Filament\Support\Fields;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

/**
 * Project editor laid out as in the approved canvas: content on the left (basics, case study, media),
 * publishing and metadata in a right-hand column.
 */
class ProjectForm
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Basics')->columns(2)->schema([
                    ...Fields::titleAndSlug(urlPrefix: 'projects'),
                    Textarea::make('summary')->rows(2)->maxLength(500)->columnSpanFull()
                        ->helperText('Shown on the home page and in the project archive.'),
                    TextInput::make('client_name')->label('Client')->maxLength(255),
                    TextInput::make('sector')->maxLength(255),
                    TextInput::make('role')->label('My role')->maxLength(255)->placeholder('e.g. Developer and maintainer'),
                    TextInput::make('year')->numeric()->minValue(2000)->maxValue(2100),
                    DatePicker::make('started_on')->label('Started')->native(false)->displayFormat('M Y'),
                    DatePicker::make('ended_on')->label('Finished')->native(false)->displayFormat('M Y')
                        ->afterOrEqual('started_on')->placeholder('Ongoing'),
                ]),

                Section::make('Case study')
                    ->headerActions([])
                    ->schema([
                        Toggle::make('has_case_study')->label('Has case study')->live()
                            ->helperText('Off: the project links to its live site instead of a case study page.'),
                        Tabs::make('Case study sections')->contained(false)
                            ->visible(fn (Get $get): bool => (bool) $get('has_case_study'))
                            ->tabs([
                                Tab::make('Problem')->schema([
                                    MarkdownField::make('problem_md')->hiddenLabel()
                                        ->placeholder('What was the client’s situation before? What needed to change, and why did it matter to the business?'),
                                ]),
                                Tab::make('Approach')->schema([
                                    MarkdownField::make('approach_md')->hiddenLabel()
                                        ->placeholder('How did you solve it? Structure, decisions, integrations, and how the team updates content.'),
                                ]),
                                Tab::make('Results')->schema([
                                    MarkdownField::make('results_md')->hiddenLabel()
                                        ->placeholder('What changed afterwards? A short outcome or client quote.'),
                                ]),
                            ]),
                    ]),

                Section::make('Media')->schema([
                    Grid::make(2)->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Cover image')
                            ->collection('cover')
                            ->disk('public')
                            ->image()
                            ->imageEditor()
                            ->maxSize(8192)
                            ->acceptedFileTypes(self::IMAGE_TYPES),
                        TextInput::make('cover_alt')
                            ->label('Alt text')
                            ->maxLength(200)
                            ->dehydrated(false)
                            ->requiredWith('cover')
                            ->helperText('Describe the image for screen readers.'),
                    ]),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->disk('public')
                        ->multiple()
                        ->reorderable()
                        ->panelLayout('grid')
                        ->image()
                        ->maxFiles(12)
                        ->maxSize(8192)
                        ->acceptedFileTypes(self::IMAGE_TYPES)
                        ->customProperties(fn (Get $get): array => ['alt' => trim($get('title').' screenshot')])
                        ->helperText('Drag to reorder. JPG, PNG, WebP or AVIF, up to 8 MB. Converted to WebP with responsive sizes.'),
                ]),
            ]),

            Group::make()->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Publishing')->schema([
                    Select::make('status')->options(PublishStatus::class)->default(PublishStatus::Draft)->required()->native(false)->selectablePlaceholder(false),
                    Toggle::make('is_featured')->label('Featured on home page'),
                    TextInput::make('sort_order')->label('Sort order')->numeric()->default(0)->minValue(0)
                        ->helperText('Lower numbers appear first.'),
                ]),

                Section::make('Results metrics')->schema([
                    Repeater::make('metrics')->hiddenLabel()
                        ->table([
                            TableColumn::make('Metric'),
                            TableColumn::make('Value')->width('35%'),
                        ])
                        ->schema([
                            TextInput::make('label')->extraInputAttributes(['aria-label' => 'Metric label'])->placeholder('e.g. Page load')->required()->maxLength(60),
                            TextInput::make('value')->extraInputAttributes(['aria-label' => 'Metric value'])->placeholder('e.g. 1.2 s')->required()->maxLength(30),
                        ])->reorderable(false)->maxItems(6)->defaultItems(0)->compact()->extraAttributes(['class' => 'cb-inline-repeater'])
                        ->addActionLabel('+ Add metric')->addActionAlignment(Alignment::Start)
                        ->addAction(fn (Action $action) => $action->link()->size('sm'))
                        ->helperText('Only real, verifiable numbers.'),
                ]),

                Section::make('Tech stack')->schema([
                    Fields::tags('Technologies')->hiddenLabel()->placeholder('Add…'),
                ]),

                Section::make('Links')->schema([
                    TextInput::make('live_url')->label('Live site')->url()->maxLength(512),
                    TextInput::make('repo_url')->label('Repository')->url()->maxLength(512)->placeholder('https://github.com/…'),
                ]),

                Section::make('SEO')->schema([
                    Fields::seoTitle(),
                    Textarea::make('seo_description')->label('Meta description')->rows(3)->maxLength(160)->live(debounce: 500)
                        ->placeholder('Leave empty to use the summary.')
                        ->hint(fn (?string $state): string => mb_strlen((string) $state).' / 160'),
                ]),
            ]),
        ]);
    }
}
