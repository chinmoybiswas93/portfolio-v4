<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('client'))
            ->columns([
                TextColumn::make('number')->placeholder('Draft')->searchable()->sortable()->weight('medium'),
                TextColumn::make('client.company_name')->label('Client')->searchable(),
                TextColumn::make('issue_date')->label('Issued')->date('j M Y')->sortable(),
                TextColumn::make('due_date')->label('Due')->date('j M Y')->sortable(),
                TextColumn::make('total_minor')->label('Total')->alignEnd()
                    ->formatStateUsing(fn (Invoice $record): string => Money::format($record->total_minor, $record->currency))
                    ->description(fn (Invoice $record): string => $record->currency->value),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('currency')->options(collect(Currency::cases())->mapWithKeys(fn (Currency $c): array => [$c->value => $c->value])),
                SelectFilter::make('year')->label('Year')
                    ->options(fn (): array => Invoice::query()->whereNotNull('sequence_year')->distinct()->orderByDesc('sequence_year')->pluck('sequence_year', 'sequence_year')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('sequence_year', $data['value']) : $query),
            ])
            ->recordActions([EditAction::make()->label('Open')]);
    }
}
