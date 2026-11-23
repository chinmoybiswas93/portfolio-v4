<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Support\StandardEditLayout;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use StandardEditLayout;

    protected static string $resource = ServiceResource::class;
}
