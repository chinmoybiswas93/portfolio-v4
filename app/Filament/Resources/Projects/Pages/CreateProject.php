<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\StandardCreateLayout;
use App\Filament\Support\SyncsCoverAlt;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use StandardCreateLayout;
    use SyncsCoverAlt;

    protected static string $resource = ProjectResource::class;
}
