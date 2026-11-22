<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;

/**
 * Create pages follow the common edit layout: Cancel and Create in the page header, nothing under the form.
 */
trait StandardCreateLayout
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')->label('Cancel')->color('gray')->outlined()
                ->url(fn (): string => static::getResource()::getUrl('index')),
            $this->getCreateFormAction()->formId('form')->label('Create'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
