<?php

declare(strict_types=1);

namespace App\Filament\Pages\Mcp;

use App\Filament\Support\StandardTable;
use App\Models\McpAuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use UnitEnum;

/**
 * Connected apps (canvas "Admin-Connected-Apps"): each MCP app with its approved permissions, last use and a
 * Revoke action that ends its access at once (ADR-0009 §2). An app is active while it holds approved scopes.
 */
class ConnectedApps extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'MCP';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'mcp/apps';

    protected static ?string $title = 'Connected apps';

    protected string $view = 'filament.pages.mcp.table-page';

    public string $footnote = 'Revoking ends the app\'s access and refresh tokens at once. The app must ask again, and you approve it in the browser.';

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
            'active' => Tab::make('Active')->modifyQueryUsing(fn (Builder $query) => $query->whereJsonLength('scopes', '>', 1)),
            'revoked' => Tab::make('Revoked')->modifyQueryUsing(fn (Builder $query) => $query
                ->where(fn (Builder $w) => $w->whereNull('scopes')->orWhereJsonLength('scopes', '<=', 1))
                ->whereHas('tokens')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->modifyQueryWithActiveTab(Client::query()))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search apps')
            ->columns([
                TextColumn::make('name')->label('App')->weight('medium')->searchable()
                    ->description(fn (Client $record): string => self::host($record)),
                TextColumn::make('scopes')->label('Scopes')->badge()->color('gray')->fontFamily('mono')
                    ->getStateUsing(fn (Client $record): array => self::grantedScopes($record)),
                TextColumn::make('created_at')->label('Approved')->date('j M Y'),
                TextColumn::make('last_used')->label('Last used')->placeholder('—')
                    ->getStateUsing(function (Client $record): ?string {
                        $at = McpAuditLog::query()->where('client_id', $record->getKey())->max('created_at');

                        return $at === null ? null : Carbon::parse($at)->diffForHumans();
                    }),
                TextColumn::make('status')->badge()
                    ->getStateUsing(fn (Client $record): string => self::status($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Not approved' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordActions(StandardTable::rowActions([
                Action::make('revoke')->label('Revoke')->color('danger')->icon(Heroicon::NoSymbol)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Client $record): string => 'Revoke '.$record->getAttribute('name').'?')
                    ->modalDescription($this->footnote)
                    ->visible(fn (Client $record): bool => self::status($record) === 'Active')
                    ->action(function (Client $record): void {
                        self::revoke($record);
                        Notification::make()->success()->title($record->getAttribute('name').' revoked')->send();
                    }),
            ]))
            ->emptyStateHeading('No apps yet')
            ->emptyStateDescription('Apps appear here after they register through /mcp.');
    }

    /** Ends every access and refresh token of the app and clears its approval, so it must ask again. */
    public static function revoke(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $tokenIds = $client->tokens()->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            $client->tokens()->update(['revoked' => true]);
            $client->forceFill(['scopes' => null])->save();
        });
    }

    /**
     * @return list<string>
     */
    private static function grantedScopes(Client $client): array
    {
        return array_values(array_diff((array) $client->getAttribute('scopes'), [Registrar::OAUTH_SCOPE]));
    }

    private static function status(Client $client): string
    {
        if (self::grantedScopes($client) !== []) {
            return 'Active';
        }

        return $client->tokens()->exists() ? 'Revoked' : 'Not approved';
    }

    private static function host(Client $client): string
    {
        $uri = (string) (((array) $client->getAttribute('redirect_uris'))[0] ?? '');
        $host = parse_url($uri, PHP_URL_HOST);

        return is_string($host) ? $host : $uri;
    }
}
