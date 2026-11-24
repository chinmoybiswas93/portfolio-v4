<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Tag;
use App\Support\Seo\Seo;
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
                ->maxLength(255)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->validationMessages([
                    'regex' => 'Use lowercase letters, numbers and single hyphens, e.g. my-first-post.',
                    'unique' => 'This slug is already used. Try another one.',
                ])
                ->unique(ignoreRecord: true)
                ->placeholder('Made from the '.($titleField === 'title' ? 'title' : 'name').' when left empty')
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, TextInput $component, $livewire): void {
                    // Normalise ("My Post!" -> "my-post"), then validate at once so errors show inline.
                    $normalised = Str::slug((string) $state);
                    if ($normalised !== (string) $state) {
                        $set('slug', $normalised);
                    }
                    $livewire->validateOnly($component->getStatePath());
                })
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

    /** One shared tag pool for posts, projects and experience; the label is only what the field is called. */
    public static function tags(string $label = 'Tags'): Select
    {
        return Select::make('tags')
            ->label($label)
            ->relationship('tags', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'))
            ->multiple()
            ->searchable()
            ->preload()
            ->createOptionForm([TextInput::make('name')->required()->maxLength(64)])
            ->createOptionUsing(fn (array $data): int => Tag::findOrCreateByName((string) $data['name'])->getKey());
    }

    public static function seoTitle(): TextInput
    {
        return TextInput::make('seo_title')
            ->label('SEO title')
            ->maxLength(Seo::TITLE_MAX)->live(debounce: 500)
            ->hint(fn (?string $state): string => mb_strlen((string) $state).' / '.Seo::TITLE_MAX)
            ->helperText('Shown in search results. Your name is added when it still fits in 60 characters. Leave empty to use the title.');
    }

    public static function seoDescription(): TextInput
    {
        return TextInput::make('seo_description')
            ->label('SEO description')
            ->maxLength(160)
            ->helperText('Up to 160 characters, shown in search results.');
    }
}
