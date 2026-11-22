<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\TagType;
use App\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Reusable admin form fields.
 */
final class Fields
{
    /**
     * Title that fills an empty slug, and a unique, URL-safe slug.
     *
     * @return list<TextInput>
     */
    public static function titleAndSlug(string $titleField = 'title'): array
    {
        return [
            TextInput::make($titleField)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    if (blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->helperText('Used in the page address. Changing it later breaks old links unless you add a redirect.'),
        ];
    }

    public static function tags(TagType $type, string $label = 'Tags'): Select
    {
        return Select::make('tags')
            ->label($label)
            ->relationship(
                'tags',
                'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', $type->value)->orderBy('name'),
            )
            ->multiple()
            ->searchable()
            ->preload()
            ->createOptionForm([TextInput::make('name')->required()->maxLength(64)])
            ->createOptionUsing(fn (array $data): int => Tag::findOrCreateByName((string) $data['name'], $type)->getKey());
    }

    public static function seoTitle(): TextInput
    {
        return TextInput::make('seo_title')
            ->label('SEO title')
            ->maxLength(70)
            ->helperText('Up to 70 characters. Leave empty to use the title.');
    }

    public static function seoDescription(): TextInput
    {
        return TextInput::make('seo_description')
            ->label('SEO description')
            ->maxLength(160)
            ->helperText('Up to 160 characters, shown in search results.');
    }
}
