<?php

declare(strict_types=1);

namespace App\Filament\Resources\Experiences\Pages;

use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Support\StandardEditLayout;
use Filament\Resources\Pages\EditRecord;

class EditExperience extends EditRecord
{
    use StandardEditLayout;

    protected static string $resource = ExperienceResource::class;
}
