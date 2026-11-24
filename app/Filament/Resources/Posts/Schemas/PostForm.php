<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Filament\Forms\MarkdownField;
use App\Filament\Support\Fields;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(2)->schema([
                Section::make()->schema([
                    ...Fields::titleAndSlug(urlPrefix: 'blog'),
                    Textarea::make('excerpt')->rows(2)->maxLength(500)
                        ->helperText('One sentence for the list page and RSS.'),
                    MarkdownField::make('body_md')->label('Body')->required()->defaultHeight(480)
                        ->helperText('Markdown. Code blocks with a language (```php) are highlighted.'),
                ]),
                // Answer-ready summary (ADR-0010, slice 8c): every part optional.
                Section::make('Summary and FAQ')->compact()
                    ->description('Optional. Shown on the post and read by search engines and AI answers.')
                    ->schema([
                        Repeater::make('takeaways')->label('Key takeaways')->simple(
                            TextInput::make('text')->required()->maxLength(160)->extraInputAttributes(['aria-label' => 'Takeaway']),
                        )->maxItems(5)->defaultItems(0)->reorderable()->compact()
                            ->addActionLabel('+ Add takeaway')->addActionAlignment(Alignment::Start)
                            ->addAction(fn (Action $action) => $action->link()->size('sm'))
                            ->helperText('Up to 5 one-line points, shown in a box under the lead.'),
                        Fields::faq(),
                        DatePicker::make('reviewed_at')->label('Last reviewed')->native(false)->displayFormat('j M Y')
                            ->maxDate(now())
                            ->helperText('Set it when you update the content. The post then shows "Updated" with this date; typo fixes don\'t need it.'),
                    ]),
                Section::make('SEO')->schema([
                    Fields::seoTitle(),
                    Fields::seoDescription(),
                ]),
            ]),
            Group::make()->schema([
                Section::make('Publishing')->schema([
                    Select::make('status')->options(PostStatus::class)->default(PostStatus::Draft)->required(),
                    DateTimePicker::make('published_at')->native(false)
                        ->helperText('Scheduled posts go live at this time.'),
                    Toggle::make('is_featured')->label('Featured'),
                ]),
                Section::make('Organise')->schema([
                    Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                    Fields::tags(),
                ]),
                Section::make('Cover image')->schema([
                    SpatieMediaLibraryFileUpload::make('cover')->collection('cover')->disk('public')->image()->imageEditor()
                        ->maxSize(8192)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']),
                    TextInput::make('cover_alt')->label('Alt text')->maxLength(200)->dehydrated(false)->requiredWith('cover'),
                ]),
            ]),
        ]);
    }
}
