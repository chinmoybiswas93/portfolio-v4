<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Filament\Support\SettingsTab;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSeo extends SettingsPage
{
    use SettingsTab;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'seo';

    protected static ?string $navigationLabel = 'SEO';

    protected static ?string $title = 'SEO';

    protected static string $settings = SeoSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Defaults')->columnSpanFull()->description('Used when a page has no title or description of its own.')->schema([
                TextInput::make('site_title')->required()->maxLength(70),
                TextInput::make('default_description')->required()->maxLength(160)->helperText('Up to 160 characters.'),
                FileUpload::make('default_og_image_path')->label('Default share image')
                    ->disk('public')->directory('seo')->image()->maxSize(4096)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                    ->helperText('1200 × 630 px. Used when a page has no generated image.'),
            ]),
        ]);
    }
}
