<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Spatie\MediaLibrary\HasMedia;

/**
 * Keeps the cover image's alt text (a media custom property) in sync with the form's cover_alt field.
 * Used by create/edit pages of resources whose model has a "cover" media collection.
 */
trait SyncsCoverAlt
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof HasMedia) {
            $data['cover_alt'] = $record->getFirstMedia('cover')?->getCustomProperty('alt');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncCoverAlt();
    }

    protected function afterCreate(): void
    {
        $this->syncCoverAlt();
    }

    private function syncCoverAlt(): void
    {
        $record = $this->getRecord();
        $alt = trim((string) ($this->data['cover_alt'] ?? ''));
        if (! $record instanceof HasMedia || $alt === '') {
            return;
        }

        // Reload: the media relation may have been loaded (empty) before the upload was attached.
        $record->load('media');
        $media = $record->getFirstMedia('cover');
        if ($media !== null && $media->getCustomProperty('alt') !== $alt) {
            $media->setCustomProperty('alt', $alt)->save();
        }
    }
}
