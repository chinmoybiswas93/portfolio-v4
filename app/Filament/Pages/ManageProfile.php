<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Filament\Support\SettingsTab;
use App\Settings\ProfileSettings;
use App\Support\Markdown\MarkdownRenderer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class ManageProfile extends SettingsPage
{
    use SettingsTab;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'profile';

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Profile';

    protected static string $settings = ProfileSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Introduction')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('title')->required()->maxLength(120)->helperText('Shown under your name, e.g. "WordPress & Laravel developer".'),
                Textarea::make('tagline')->required()->rows(2)->maxLength(220)->columnSpanFull(),
                MarkdownEditor::make('about_md')->label('About')->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList'], ['undo', 'redo']]),
            ]),
            Section::make('Contact')->columns(2)->columnSpanFull()->schema([
                TextInput::make('email')->email()->maxLength(255)->helperText('Public contact address.'),
                TextInput::make('location_public')->label('Location')->maxLength(120)
                    ->helperText('City and country only, e.g. "Remote · UK & EU hours". Never a street address.'),
                Repeater::make('socials')->columnSpanFull()->reorderable()->maxItems(6)->defaultItems(0)->compact()
                    ->table([TableColumn::make('Network')->width('30%'), TableColumn::make('Link')])
                    ->addActionLabel('+ Add link')->addActionAlignment(Alignment::Start)
                    ->addAction(fn (Action $action) => $action->link()->size('sm'))
                    ->schema([
                        Select::make('network')->required()->options(['github' => 'GitHub', 'linkedin' => 'LinkedIn', 'x' => 'X (Twitter)', 'email' => 'Email']),
                        TextInput::make('url')->required()->maxLength(255)->rule('regex:#^(https://|mailto:)#')
                            ->helperText('https://… or mailto:…'),
                    ]),
            ]),
            Section::make('Files')->columns(2)->columnSpanFull()->schema([
                FileUpload::make('cv_path')->label('CV (PDF)')
                    ->disk('local')->directory('cv')->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])->maxSize(10240)
                    ->helperText('Served at /cv. Stored privately, never directly web-accessible.'),
                FileUpload::make('avatar_path')->label('Profile photo')
                    ->disk('public')->directory('profile')->image()->imageEditor()->maxSize(4096)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['about_html'] = app(MarkdownRenderer::class)->render((string) ($data['about_md'] ?? ''));

        return $data;
    }
}
