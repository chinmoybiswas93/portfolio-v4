<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

/**
 * List page with create and edit in a modal (canvas v17, "Admin-Modal-Resource").
 */
class ManageClients extends ManageRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New client')->modalHeading('New client')->modalWidth(Width::Large)];
    }
}
