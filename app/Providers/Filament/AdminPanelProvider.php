<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\StandardTable;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Filament\SystemFontProvider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Single-owner admin (ADR-0007): login + required TOTP with recovery codes, no registration or
 * password reset (recovery is `php artisan app:admin-reset` over SSH).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('admin.path'))
            ->login(Login::class)
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Chinmoy Biswas'),
            ], isRequired: true)
            ->brandName('Chinmoy Biswas')
            ->brandLogo(asset('brand/logo-mark-dark.svg'))
            ->darkModeBrandLogo(asset('brand/logo-mark-light.svg'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.svg'))
            ->font('system-ui', provider: SystemFontProvider::class, preload: [])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex('#2DD4BF'),
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->navigationGroups([
                NavigationGroup::make('Content')->collapsible(false),
                NavigationGroup::make('Billing')->collapsible(false),
                NavigationGroup::make('Site')->collapsible(false),
            ])
            ->sidebarWidth('14.5rem')
            ->maxContentWidth(Width::Full)
            ->globalSearchKeyBindings(['mod+k'])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.view-site-link')->render())
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        StandardTable::register();
    }
}
