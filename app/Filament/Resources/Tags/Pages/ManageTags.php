<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

/**
 * List page with create and edit in a modal (canvas v17, "Admin-Modal-Resource").
 */
class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New tag')->modalHeading('New tag')->modalWidth(Width::Large)];
    }
}
