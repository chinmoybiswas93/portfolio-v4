<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\StandardCreateLayout;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    use StandardCreateLayout;

    protected static string $resource = ServiceResource::class;
}
