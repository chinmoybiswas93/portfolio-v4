<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Filament\Support\SettingsTab;
use App\Settings\BrandingSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageBranding extends SettingsPage
{
    use SettingsTab;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'branding';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?string $title = 'Branding';

    protected static string $settings = BrandingSettings::class;

    public function form(Schema $schema): Schema
    {
        $image = fn (FileUpload $upload, array $types): FileUpload => $upload
            ->disk('public')->directory('brand')->maxSize(2048)->acceptedFileTypes($types);

        return $schema->components([
            Section::make('Logo and icons')->columnSpanFull()->description('Leave a field empty to use the bundled file.')->schema([
                $image(FileUpload::make('logo_path')->label('Logo mark')->helperText('SVG or PNG with dark artwork. Used on invoice PDFs (white paper).'), ['image/svg+xml', 'image/png']),
                $image(FileUpload::make('admin_logo_path')->label('Admin logo, light theme')->helperText('SVG or PNG with dark artwork, for the light admin header.'), ['image/svg+xml', 'image/png']),
                $image(FileUpload::make('admin_logo_dark_path')->label('Admin logo, dark theme')->helperText('SVG or PNG with light artwork, for the dark admin header and the sign-in approval screens.'), ['image/svg+xml', 'image/png']),
                $image(FileUpload::make('favicon_path')->label('Favicon')->helperText('SVG, PNG or ICO.'), ['image/svg+xml', 'image/png', 'image/x-icon', 'image/vnd.microsoft.icon']),
                $image(FileUpload::make('apple_touch_path')->label('Touch icon')->helperText('180 × 180 px PNG.'), ['image/png']),
                $image(FileUpload::make('og_logo_path')->label('Share-image logo')->helperText('PNG with a transparent background, about 70 px wide. Drawn on generated share images.'), ['image/png']),
            ]),
        ]);
    }
}
