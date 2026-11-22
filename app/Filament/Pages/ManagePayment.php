<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\PaymentSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManagePayment extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 13;

    protected static ?string $title = 'Business & payment';

    protected static string $settings = PaymentSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Business details')->columns(2)->columnSpanFull()
                ->description('Printed on invoices. Every field here is stored encrypted.')
                ->schema([
                    TextInput::make('business_name')->maxLength(120),
                    TextInput::make('business_email')->email()->maxLength(255),
                    Textarea::make('business_address')->rows(3)->maxLength(400)->columnSpanFull(),
                ]),
            Section::make('How clients pay you')->columns(2)->columnSpanFull()
                ->description('Leave a method empty to hide it on invoices.')
                ->schema([
                    Textarea::make('bank_details')->label('Bank transfer')->rows(4)->maxLength(600)
                        ->placeholder("Account name\nSort code / IBAN\nAccount number / BIC"),
                    Textarea::make('wise_details')->label('Wise')->rows(4)->maxLength(600),
                    TextInput::make('paypal_email')->label('PayPal email')->email()->maxLength(255),
                ]),
        ]);
    }
}
