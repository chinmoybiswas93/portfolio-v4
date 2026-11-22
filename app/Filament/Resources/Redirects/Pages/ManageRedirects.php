<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

/**
 * List page with create and edit in a modal (canvas v17, "Admin-Modal-Resource").
 */
class ManageRedirects extends ManageRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New redirect')->modalHeading('New redirect')->modalWidth(Width::Large)];
    }
}
