<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use App\Domain\Invoicing\TaxRules;
use App\Enums\Currency;
use App\Filament\Support\Countries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company')->contained(false)->compact()->columns(2)->columnSpanFull()->schema([
                TextInput::make('company_name')->required()->maxLength(255),
                TextInput::make('contact_name')->maxLength(255),
                TextInput::make('email')->label('Billing email')->email()->required()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(64),
                Select::make('default_currency')->options(collect(Currency::cases())->mapWithKeys(fn (Currency $c): array => [$c->value => $c->value.' ('.$c->symbol().')']))
                    ->default(Currency::GBP->value)->required()->native(false)->selectablePlaceholder(false),
            ]),
            Section::make('Address and tax')->contained(false)->compact()->columns(2)->columnSpanFull()->schema([
                TextInput::make('address_line1')->label('Address line 1')->maxLength(255),
                TextInput::make('address_line2')->label('Address line 2')->maxLength(255),
                TextInput::make('city')->maxLength(255),
                TextInput::make('postcode')->maxLength(32),
                Select::make('country_code')->label('Country')->options(Countries::options())->searchable()->live(),
                TextInput::make('vat_number')
                    ->label(fn (Get $get): string => TaxRules::clientTaxIdLabel($get('country_code')).' (optional)')
                    ->maxLength(64)
                    ->helperText('Printed on invoices. UK/EU business with a VAT ID at 0% VAT: the invoice adds the reverse-charge note.'),
                Textarea::make('notes')->label('Private notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
