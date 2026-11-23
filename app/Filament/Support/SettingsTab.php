<?php

declare(strict_types=1);

namespace App\Filament\Support;

/**
 * Shared behaviour for the Settings tabs: "Save changes" in the page header, no footer buttons.
 */
trait SettingsTab
{
    protected function getHeaderActions(): array
    {
        return [$this->getSaveFormAction()->label('Save changes')->formId('form')];
    }

    public function getHeading(): string
    {
        return 'Settings';
    }

    public function getFormActions(): array
    {
        return [];
    }
}
