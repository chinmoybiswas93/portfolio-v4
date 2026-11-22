<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Filament\Pages\Mcp\ConnectedApps;
use App\Filament\Pages\Mcp\McpAuditLogPage;
use App\Filament\Support\SettingsTab;
use App\Settings\McpSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Settings → MCP tab (canvas "Admin-Settings-MCP"): the everyday switches. MCP_ENABLED in .env is the hard switch.
 */
class ManageMcp extends SettingsPage
{
    use SettingsTab;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 5;

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'mcp';

    protected static ?string $navigationLabel = 'MCP';

    protected static ?string $title = 'MCP';

    protected static string $settings = McpSettings::class;

    public function form(Schema $schema): Schema
    {
        $url = url('/mcp');

        return $schema->components([
            Section::make('MCP server')->columnSpanFull()
                ->description(new HtmlString('Lets AI assistants you approve read and edit content through <code>/mcp</code>. Payment, invoicing and security settings are never available to them.'))
                ->schema([
                    Toggle::make('enabled')->label('MCP enabled')
                        ->helperText(config('mcp-server.enabled')
                            ? 'Off: every request to /mcp gets 503 and no tools run.'
                            : 'MCP_ENABLED is off in the server .env, so MCP stays off whatever this switch says.'),
                    TextInput::make('server_url')->label('Server URL')->readOnly()->dehydrated(false)
                        ->afterStateHydrated(fn (TextInput $component) => $component->state($url))
                        ->suffixAction(Action::make('copy')->label('Copy')->icon(Heroicon::OutlinedClipboard)
                            ->alpineClickHandler('navigator.clipboard.writeText('.json_encode($url).')')),
                    TextEntry::make('rate_limit')->label('Rate limit per app')
                        ->state(number_format((int) config('mcp-server.rate.per_minute')).' calls a minute, '.number_format((int) config('mcp-server.rate.per_day')).' a day'),
                    TextEntry::make('token_lifetime')->label('Access token lifetime')->state('1 hour (refresh for 30 days)'),
                    Radio::make('registration_open')->label('New app registration')
                        ->boolean('Allowed, I approve each app', 'Closed')->inline(),
                    TextEntry::make('scopes')->label('Scopes apps can ask for')->badge()->color('gray')->fontFamily('mono')
                        ->state(array_keys((array) config('mcp-server.scopes'))),
                    Text::make(new HtmlString('<a class="text-primary-400 hover:underline" href="'.e(ConnectedApps::getUrl()).'">Connected apps</a> · <a class="text-primary-400 hover:underline" href="'.e(McpAuditLogPage::getUrl()).'">Audit log</a>')),
                ]),
        ]);
    }
}
