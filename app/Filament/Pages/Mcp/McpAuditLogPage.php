<?php

declare(strict_types=1);

namespace App\Filament\Pages\Mcp;

use App\Models\McpAuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * MCP audit log (canvas "Admin-MCP-Audit"): every tool call, including denied and throttled ones. Read only.
 */
class McpAuditLogPage extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'MCP';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'mcp/audit';

    protected static ?string $title = 'Audit log';

    protected string $view = 'filament.pages.mcp.table-page';

    public string $footnote = 'Every tool call is logged, including denied and throttled ones. Open a row for the before/after summary.';

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'changes' => Tab::make('Changes')->modifyQueryUsing(fn (Builder $query) => $query->where('result', 'ok')->whereNotNull('changes')),
            'denied' => Tab::make('Denied')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('result', ['denied', 'throttled', 'error'])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->modifyQueryWithActiveTab(McpAuditLog::query()))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Tool or target')
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('client_name')->label('App')->placeholder('—'),
                TextColumn::make('tool')->fontFamily('mono')->searchable(),
                TextColumn::make('target_label')->label('Target')->placeholder('—')->searchable()
                    ->formatStateUsing(fn (McpAuditLog $record): string => $record->targetText()),
                TextColumn::make('change')->label('Change')->placeholder('—')->wrap()
                    ->getStateUsing(fn (McpAuditLog $record): ?string => $record->changeText()),
                TextColumn::make('ip')->label('IP')->fontFamily('mono'),
                TextColumn::make('result')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'ok' ? 'OK' : ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'denied', 'error' => 'danger',
                        'throttled' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('client_name')->label('App')
                    ->options(fn (): array => McpAuditLog::query()->whereNotNull('client_name')->distinct()->orderBy('client_name')->pluck('client_name', 'client_name')->all()),
                SelectFilter::make('period')->label('Period')
                    ->options(['7' => 'Last 7 days', '30' => 'Last 30 days'])->placeholder('All time')
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('created_at', '>=', now()->subDays((int) $data['value'])) : $query),
            ])
            ->recordAction('details')
            ->recordActions([
                Action::make('details')->label('Details')->icon(Heroicon::Eye)->color('gray')->iconButton()
                    ->modalHeading(fn (McpAuditLog $record): string => $record->tool.' · '.$record->created_at->format('j M Y, H:i'))
                    ->modalContent(fn (McpAuditLog $record): View => view('filament.pages.mcp.audit-details', ['log' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('No MCP calls yet');
    }

    /** CSV of the rows matching the current tab, filters and search. */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery() ?? McpAuditLog::query();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['time', 'app', 'tool', 'scope', 'target', 'change', 'result', 'http_status', 'ip'], escape: '');
            $query->clone()->lazyById(500)->each(function (Model $log) use ($out): void {
                if (! $log instanceof McpAuditLog) {
                    return;
                }
                fputcsv($out, array_map(self::csvCell(...), [
                    $log->created_at->toIso8601String(), $log->client_name, $log->tool, $log->scope,
                    $log->targetText(), $log->changeText(), $log->result, (string) $log->http_status, $log->ip,
                ]), escape: '');
            });
            fclose($out);
        }, 'mcp-audit-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet formula injection: text written by an assistant could start with = + - @. */
    public static function csvCell(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
