<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\StandardEditLayout;
use App\Filament\Support\SyncsCoverAlt;
use App\Models\Project;
use Filament\Resources\Pages\EditRecord;

/**
 * Header as in the approved canvas: title and status, then Preview, Save and Save and publish.
 */
class EditProject extends EditRecord
{
    use StandardEditLayout;
    use SyncsCoverAlt;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return $this->project()->title;
    }

    public function getSubheading(): string
    {
        return $this->project()->status->getLabel();
    }

    private function project(): Project
    {
        /** @var Project $record */
        $record = $this->getRecord();

        return $record;
    }

    protected function previewUrl(): ?string
    {
        return $this->project()->status === PublishStatus::Published ? $this->project()->primaryUrl() : null;
    }
}
