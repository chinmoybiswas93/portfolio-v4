<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\TagType;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Reusable admin form fields.
 */
final class Fields
{
    /**
     * Title plus slug. On create the slug fills itself from the title while it is empty; once the record is
     * saved the slug is read-only, with an Edit link under it that unlocks it (changing it breaks old links).
     *
     * @param  string|null  $urlPrefix  Public path before the slug (e.g. "projects"), shown as a URL preview.
     * @return list<TextInput|Hidden>
     */
    public static function titleAndSlug(string $titleField = 'title', ?string $urlPrefix = null): array
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return [
            TextInput::make($titleField)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                    if ($operation === 'create' && blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            Hidden::make('slug_unlocked')->default(false)->dehydrated(false),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->readOnly(fn (string $operation, Get $get): bool => $operation === 'edit' && ! $get('slug_unlocked'))
                ->belowContent(fn (Get $get, string $operation): Schema => Schema::between([
                    $urlPrefix !== null
                        ? $host.'/'.$urlPrefix.'/'.($get('slug') ?: '…')
                        : 'Used in links and page addresses.',
                    $operation === 'edit' && $get('slug_unlocked')
                        ? 'Changing it breaks old links. Add a redirect.'
                        : Action::make('editSlug')
                            ->label('Edit')
                            ->link()
                            ->icon(Heroicon::PencilSquare)
                            ->visible($operation === 'edit')
                            ->action(fn (Set $set) => $set('slug_unlocked', true)),
                ])),
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
