<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Domain\Invoicing\InvalidInvoice;
use App\Domain\Invoicing\InvoiceCalculator;
use App\Domain\Invoicing\InvoiceLine;
use App\Domain\Invoicing\TaxRules;
use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\EmailStatus;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Models\Client;
use App\Models\Invoice;
use App\Settings\InvoiceSettings;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Invoice editor laid out as in the approved canvas, with tax on the total only (owner decision 2026-11-22).
 * Money is typed as decimals and stored as integer minor units; quantities as thousandths.
 */
class InvoiceForm
{
    private const DECIMAL_2 = 'regex:/^\d{1,11}(\.\d{1,2})?$/';

    private const DECIMAL_3 = 'regex:/^\d{1,9}(\.\d{1,3})?$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->disabled(fn (?Invoice $record): bool => $record?->status->isLocked() ?? false)->components([
            Group::make()->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Details')->columns(2)->schema([
                    Select::make('client_id')->label('Client')->relationship('client', 'company_name')->required()->searchable()->preload()->live()
                        ->createOptionForm(fn (Schema $schema): Schema => ClientForm::configure($schema))
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $client = $state ? Client::query()->find($state) : null;
                            if ($client) {
                                $set('currency', $client->default_currency->value);
                            }
                        }),
                    Select::make('currency')->required()->native(false)->selectablePlaceholder(false)->live()
                        ->options(collect(Currency::cases())->mapWithKeys(fn (Currency $c): array => [$c->value => $c->value.' ('.$c->symbol().')']))
                        ->default(fn (): string => app(InvoiceSettings::class)->default_currency)
                        ->helperText('Taken from the client’s default.'),
                    DatePicker::make('issue_date')->native(false)->displayFormat('j M Y')->default(now())
                        ->helperText('Leave as is to use today’s date on issue.'),
                    DatePicker::make('due_date')->native(false)->displayFormat('j M Y')->afterOrEqual('issue_date')
                        ->default(fn () => now()->addDays(app(InvoiceSettings::class)->default_due_days))
                        ->helperText(fn (): string => app(InvoiceSettings::class)->default_due_days.' days, from your invoicing settings.'),
                    Placeholder::make('number_preview')->label('Invoice number')
                        ->content(fn (?Invoice $record): string => $record->number ?? 'Assigned on issue'),
                    TextInput::make('reference')->label('Project / reference')->maxLength(255)
                        ->placeholder('e.g. Website care plan — November 2026'),
                ]),

                Section::make('Line items')->schema([
                    Repeater::make('items')->hiddenLabel()->relationship()->orderColumn('position')
                        ->table([
                            TableColumn::make('Description'),
                            TableColumn::make('Qty')->width('90px'),
                            TableColumn::make('Unit price')->width('130px'),
                            TableColumn::make('Line total')->width('120px'),
                        ])
                        ->schema([
                            Textarea::make('description')->extraInputAttributes(['aria-label' => 'Description'])->rows(1)->autosize()->required()->maxLength(2000)
                                ->placeholder('Title, then an optional second line with details'),
                            TextInput::make('quantity_milli')->extraInputAttributes(['aria-label' => 'Quantity'])->required()->default('1')->rule(self::DECIMAL_3)->live(onBlur: true)
                                ->formatStateUsing(fn (mixed $state): string => is_int($state) ? Money::toDecimalString($state, 3, trim: true) : (string) ($state ?? '1'))
                                ->dehydrateStateUsing(fn (mixed $state): int => Money::parseDecimal($state, 3) ?? 0),
                            TextInput::make('unit_price_minor')->extraInputAttributes(['aria-label' => 'Unit price'])->required()->rule(self::DECIMAL_2)->live(onBlur: true)->placeholder('0.00')
                                ->formatStateUsing(fn (mixed $state): string => is_int($state) ? Money::toDecimalString($state, 2) : (string) ($state ?? ''))
                                ->dehydrateStateUsing(fn (mixed $state): int => Money::parseDecimal($state, 2) ?? 0),
                            Placeholder::make('line_total')->hiddenLabel()
                                ->content(fn (Get $get): string => self::lineTotal($get('quantity_milli'), $get('unit_price_minor'), $get('../../currency'))),
                        ])
                        ->defaultItems(1)->minItems(1)->addActionLabel('Add line')->reorderable(),
                ]),

                Section::make('Discount and tax')->columns(3)->schema([
                    Select::make('discount_type')->label('Invoice discount')->options([
                        DiscountType::None->value => 'None',
                        DiscountType::Percent->value => 'Percentage',
                        DiscountType::Fixed->value => 'Fixed amount',
                    ])->default(DiscountType::None->value)->native(false)->selectablePlaceholder(false)->live(),
                    TextInput::make('discount_value')->label('Value')->rule(self::DECIMAL_2)->default('0')->live(onBlur: true)
                        ->suffix(fn (Get $get): string => self::enumValue($get('discount_type')) === DiscountType::Percent->value ? '%' : (string) self::currency($get('currency'))?->symbol())
                        ->hidden(fn (Get $get): bool => self::enumValue($get('discount_type')) === DiscountType::None->value)
                        ->formatStateUsing(fn (mixed $state): string => is_int($state) ? Money::toDecimalString($state, 2, trim: true) : (string) ($state ?? '0'))
                        ->dehydrateStateUsing(fn (mixed $state): int => Money::parseDecimal($state, 2) ?? 0)
                        ->helperText('Applied to the subtotal, before VAT.'),
                    TextInput::make('tax_rate_bp')->label('VAT rate on the total')->suffix('%')->rule(self::DECIMAL_2)->live(onBlur: true)
                        ->default(fn (): string => Money::toDecimalString(app(InvoiceSettings::class)->default_tax_rate_bp, 2, trim: true))
                        ->formatStateUsing(fn (mixed $state): string => is_int($state) ? Money::toDecimalString($state, 2, trim: true) : (string) ($state ?? '0'))
                        ->dehydrateStateUsing(fn (mixed $state): int => Money::parseDecimal($state, 2) ?? 0)
                        ->helperText('0% while not VAT-registered.'),
                ]),

                Section::make('Notes')->schema([
                    Textarea::make('notes')->label('Notes for the client')->rows(3)->maxLength(2000)
                        ->helperText('Shown on the invoice. Payment details come from Settings › Business & payment.'),
                ]),
            ]),

            Group::make()->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Summary')->schema([
                    Placeholder::make('summary')->hiddenLabel()->content(fn (Get $get, ?Invoice $record): HtmlString => self::summary($get, $record)),
                ]),
                Section::make('Email history')->visible(fn (?Invoice $record): bool => $record !== null && $record->number !== null)->schema([
                    Placeholder::make('emails')->hiddenLabel()->content(fn (?Invoice $record): HtmlString => self::emailHistory($record)),
                ]),
            ]),
        ]);
    }

    private static function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (is_string($value) ? $value : null);
    }

    private static function currency(mixed $value): ?Currency
    {
        return $value instanceof Currency ? $value : Currency::tryFrom((string) self::enumValue($value));
    }

    private static function lineTotal(mixed $qty, mixed $price, mixed $currency): string
    {
        $q = is_int($qty) ? $qty : Money::parseDecimal($qty, 3);
        $p = is_int($price) ? $price : Money::parseDecimal($price, 2);
        $c = self::currency($currency) ?? Currency::GBP;

        return $q === null || $p === null ? '—' : Money::format(InvoiceCalculator::roundHalfUp($q * $p, 1000), $c);
    }

    private static function summary(Get $get, ?Invoice $record): HtmlString
    {
        $currency = self::currency($get('currency')) ?? Currency::GBP;
        try {
            $lines = [];
            foreach ((array) $get('items') as $item) {
                $qty = is_int($item['quantity_milli'] ?? null) ? $item['quantity_milli'] : Money::parseDecimal($item['quantity_milli'] ?? null, 3);
                $price = is_int($item['unit_price_minor'] ?? null) ? $item['unit_price_minor'] : Money::parseDecimal($item['unit_price_minor'] ?? null, 2);
                if ($qty !== null && $price !== null) {
                    $lines[] = new InvoiceLine(trim((string) ($item['description'] ?? '')) ?: 'Line', $qty, $price);
                }
            }
            $type = DiscountType::tryFrom((string) self::enumValue($get('discount_type'))) ?? DiscountType::None;
            $discount = Money::parseDecimal($get('discount_value'), 2) ?? 0;
            $rate = Money::parseDecimal($get('tax_rate_bp'), 2) ?? 0;
            $totals = (new InvoiceCalculator)->calculate($lines, $type, $discount, $rate);
        } catch (InvalidInvoice $e) {
            return new HtmlString('<p class="text-sm text-danger-600 dark:text-danger-400">'.e($e->getMessage()).'</p>');
        }

        $rows = [['Subtotal', Money::format($totals->subtotal, $currency)]];
        if ($totals->discount > 0) {
            $rows[] = ['Discount', Money::format(-$totals->discount, $currency)];
        }
        $rows[] = ['VAT ('.Money::toDecimalString($rate, 2, trim: true).'%)', Money::format($totals->tax, $currency)];

        $client = $get('client_id') ? Client::query()->find($get('client_id')) : null;
        $note = $record->tax_note ?? TaxRules::note($client?->country_code, $client?->vat_number, $rate, app(InvoiceSettings::class)->tax_note);

        $html = '<dl class="grid grid-cols-2 gap-y-2 text-sm">';
        foreach ($rows as [$label, $value]) {
            $html .= '<dt class="text-gray-500 dark:text-gray-400">'.e($label).'</dt><dd class="text-right">'.e($value).'</dd>';
        }
        $html .= '<dt class="mt-2 border-t border-gray-200 pt-3 font-semibold dark:border-white/10">Total due</dt>'
            .'<dd class="mt-2 border-t border-gray-200 pt-3 text-right text-lg font-semibold dark:border-white/10">'.e(Money::format($totals->total, $currency)).'</dd></dl>';
        if ($note) {
            $html .= '<p class="mt-4 text-xs text-gray-500 dark:text-gray-400">'.e($note).'</p>';
        }

        return new HtmlString($html);
    }

    private static function emailHistory(?Invoice $record): HtmlString
    {
        $emails = $record?->emails()->with('pdf')->latest()->get() ?? collect();
        if ($record === null || $emails->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Not emailed yet.</p>');
        }

        $badge = [
            'pending' => ['Pending', 'bg-gray-500/15 text-gray-300'],
            'sent' => ['Sent', 'bg-primary-500/15 text-primary-300'],
            'failed' => ['Failed', 'bg-danger-500/15 text-danger-300'],
        ];
        $html = '<ul class="cb-email-log">';
        foreach ($emails as $email) {
            [$label, $classes] = $badge[$email->status->value];
            $pdfLink = $email->pdf !== null
                ? '<a class="text-primary-400 hover:underline" target="_blank" rel="noopener" href="'.e(route('filament.admin.invoices.pdf', ['invoice' => $record, 'pdf' => $email->pdf])).'">PDF '.e($email->pdf->label()).' · Preview</a>'
                : '';
            $status = EmailStatus::from($email->status->value);
            $note = match ($status) {
                EmailStatus::Pending => 'Queued, sends within a minute',
                EmailStatus::Sent => 'Accepted by the mail server',
                EmailStatus::Failed => (string) $email->error,
            };
            $html .= '<li class="cb-email-log-item">'
                .'<span class="flex justify-between gap-2"><span class="font-medium">'.e($email->to_email).'</span>'
                .'<span class="rounded-full px-2 py-0.5 text-xs font-medium '.$classes.'">'.$label.'</span></span>'
                .'<span class="flex justify-between gap-2 text-xs text-gray-400"><span>'.e(($email->sent_at ?? $email->created_at)?->format('j M Y, H:i') ?? '').'</span>'.$pdfLink.'</span>'
                .'<span class="text-xs '.($status === EmailStatus::Failed ? 'text-danger-400' : 'text-gray-500').'">'.e($note).'</span>'
                .'</li>';
        }

        return new HtmlString($html.'</ul>');
    }
}
