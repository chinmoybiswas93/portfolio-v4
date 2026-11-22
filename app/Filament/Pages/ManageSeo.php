<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSeo extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 11;

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
