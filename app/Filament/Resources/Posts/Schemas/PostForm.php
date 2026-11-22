<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Enums\TagType;
use App\Filament\Support\Fields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(2)->schema([
                Section::make()->schema([
                    ...Fields::titleAndSlug(),
                    Textarea::make('excerpt')->rows(2)->maxLength(500)
                        ->helperText('One sentence for the list page and RSS.'),
                    MarkdownEditor::make('body_md')->label('Body')->required()
                        ->helperText('Markdown. Code blocks with a language (```php) are highlighted.'),
                ]),
                Section::make('SEO')->collapsed()->schema([
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
                    Fields::tags(TagType::Topic),
                ]),
                Section::make('Cover image')->schema([
                    SpatieMediaLibraryFileUpload::make('cover')->collection('cover')->image()->imageEditor()
                        ->maxSize(8192)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']),
                    TextInput::make('cover_alt')->label('Alt text')->maxLength(200)->dehydrated(false)->requiredWith('cover'),
                ]),
            ]),
        ]);
    }
}
