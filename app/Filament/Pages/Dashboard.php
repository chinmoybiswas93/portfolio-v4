<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Dashboard as in the approved canvas: greeting, quick actions, then the widgets.
 */
class Dashboard extends BaseDashboard
{
    public function getSubheading(): string
    {
        $hour = (int) now('Europe/London')->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
        $name = strtok((string) auth()->user()?->name, ' ');

        return $greeting.($name ? ', '.$name : '').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newPost')->label('New post')->icon(Heroicon::OutlinedPencilSquare)->color('gray')->url(PostResource::getUrl('create')),
            Action::make('newInvoice')->label('New invoice')->icon(Heroicon::OutlinedDocumentPlus)->url(InvoiceResource::getUrl('create')),
        ];
    }
}
