<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\SyncsCoverAlt;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use SyncsCoverAlt;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->formId('form')->label('Save draft')->color('gray'),
            Action::make('createAndPublish')
                ->label('Save and publish')
                ->action(function (): void {
                    $this->data['status'] = PublishStatus::Published->value;
                    $this->create();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
