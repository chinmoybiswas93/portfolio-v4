<?php

declare(strict_types=1);

namespace App\Filament\Resources\Experiences\Pages;

use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Support\StandardCreateLayout;
use Filament\Resources\Pages\CreateRecord;

class CreateExperience extends CreateRecord
{
    use StandardCreateLayout;

    protected static string $resource = ExperienceResource::class;
}
