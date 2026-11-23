<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\StandardCreateLayout;
use App\Filament\Support\SyncsCoverAlt;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use StandardCreateLayout;
    use SyncsCoverAlt;

    protected static string $resource = PostResource::class;
}
