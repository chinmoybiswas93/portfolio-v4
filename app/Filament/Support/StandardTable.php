<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * One table design for every admin list (owner, 2026-11-22; canvas "Admin-Invoices"): tabs and filters in the
 * toolbar, filters applied at once, a single "⋯" row-actions button, the same pagination everywhere.
 */
final class StandardTable
{
    public const PER_PAGE = 25;

    public static function register(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->deferFilters(false)
                ->paginationPageOptions([self::PER_PAGE])
                ->defaultPaginationPageOption(self::PER_PAGE)
                ->striped(false)
                ->extraAttributes(['class' => 'cb-table']);
        });

        // Compact cards everywhere (canvas v18): smaller section padding.
        Section::configureUsing(fn (Section $section) => $section->compact());

        // Modal create/edit (canvas "Admin-Modal-Resource"): one Create button, footer on the right.
        CreateAction::configureUsing(fn (CreateAction $action) => $action->createAnother(false));

        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_START, fn (): View => view('filament.table-toolbar'));
    }

    /**
     * @param  array<Action|ActionGroup>  $actions
     * @return array<ActionGroup>
     */
    public static function rowActions(array $actions): array
    {
        return [
            ActionGroup::make($actions)
                ->label('Actions')
                ->icon(Heroicon::EllipsisHorizontal)
                ->iconButton()
                ->color('gray'),
        ];
    }

    /** List page content without the tabs above the table: they are drawn in the toolbar instead. */
    public static function listContent(ListRecords $page, Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
}
