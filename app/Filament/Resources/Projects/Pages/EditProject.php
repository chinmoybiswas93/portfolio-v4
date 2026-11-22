<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\SyncsCoverAlt;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Header as in the approved canvas: title and status, then Preview, Save and Save and publish.
 */
class EditProject extends EditRecord
{
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => $this->project()->primaryUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->project()->status === PublishStatus::Published && $this->project()->primaryUrl() !== null),
            $this->getSaveFormAction()->formId('form')->label('Save')->color('gray'),
            Action::make('saveAndPublish')
                ->label('Save and publish')
                ->action(function (): void {
                    $this->data['status'] = PublishStatus::Published->value;
                    $this->save();
                }),
            ActionGroup::make([
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->label('More')->icon(Heroicon::EllipsisVertical)->color('gray'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    private function project(): Project
    {
        /** @var Project $record */
        $record = $this->getRecord();

        return $record;
    }
}
