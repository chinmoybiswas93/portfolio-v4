<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Currency;
use App\Filament\Clusters\Settings;
use App\Filament\Support\SettingsTab;
use App\Settings\InvoiceSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageInvoicing extends SettingsPage
{
    use SettingsTab;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyPound;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'invoicing';

    protected static ?string $navigationLabel = 'Invoicing';

    protected static ?string $title = 'Invoicing';

    protected static string $settings = InvoiceSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Numbering and defaults')->columns(2)->columnSpanFull()->schema([
                TextInput::make('prefix')->required()->maxLength(10)->alphaNum()->live(onBlur: true)
                    ->helperText(fn (Get $get): string => 'Next number looks like '.strtoupper((string) $get('prefix')).'-'.now()->year.'-0001. Resets every year.'),
                Select::make('default_currency')->required()->options(collect(Currency::cases())->mapWithKeys(fn (Currency $c): array => [$c->value => $c->value.' ('.$c->symbol().')'])),
                TextInput::make('default_due_days')->label('Payment terms (days)')->required()->integer()->minValue(0)->maxValue(120),
                TextInput::make('default_tax_rate_bp')->label('Default tax rate')->required()->numeric()->minValue(0)->maxValue(100)->suffix('%')
                    ->formatStateUsing(fn (?int $state): string => rtrim(rtrim(number_format(($state ?? 0) / 100, 2, '.', ''), '0'), '.'))
                    ->dehydrateStateUsing(fn (string|int|float|null $state): int => (int) round(((float) $state) * 100))
                    ->helperText('0% while not VAT-registered.'),
                TextInput::make('tax_note')->maxLength(120)->columnSpanFull()->placeholder('VAT not applicable'),
                Textarea::make('footer')->rows(2)->maxLength(500)->columnSpanFull()->helperText('Printed at the bottom of every invoice.'),
            ]),
        ]);
    }
}
