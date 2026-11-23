<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The common edit layout (canvas v17): title and breadcrumbs on the left; Cancel, Preview, Delete and Save as
 * header actions on the right; no buttons under the form. Pages override previewUrl() to show Preview.
 */
trait StandardEditLayout
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')->label('Cancel')->color('gray')->outlined()
                ->url(fn (): string => static::getResource()::getUrl('index')),
            Action::make('preview')->label('Preview')->icon(Heroicon::ArrowTopRightOnSquare)->color('gray')->outlined()
                ->url(fn (): ?string => $this->previewUrl())->openUrlInNewTab()
                ->visible(fn (): bool => $this->previewUrl() !== null),
            DeleteAction::make()->color('danger')->outlined(),
            RestoreAction::make()->color('gray')->outlined(),
            ForceDeleteAction::make()->color('danger')->outlined(),
            $this->getSaveFormAction()->formId('form')->label('Save'),
        ];
    }

    /** The heading is cut to one line in CSS; the full title shows on hover (ADR-0010). */
    public function getHeading(): Htmlable
    {
        $title = e((string) $this->getTitle());

        return new HtmlString("<span title=\"{$title}\">{$title}</span>");
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function previewUrl(): ?string
    {
        return null;
    }
}
