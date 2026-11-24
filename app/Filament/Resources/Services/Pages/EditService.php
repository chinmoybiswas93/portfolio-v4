<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\StandardEditLayout;
use App\Models\Service;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use StandardEditLayout;

    protected static string $resource = ServiceResource::class;

    protected function previewUrl(): ?string
    {
        /** @var Service $service */
        $service = $this->getRecord();

        return $service->hasPage() ? route('services.show', $service) : null;
    }
}
