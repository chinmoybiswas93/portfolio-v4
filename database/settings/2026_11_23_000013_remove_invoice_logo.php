<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** The invoice PDF always uses the brand logo; the unused upload setting is removed (Phase 4). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->delete('invoicing.logo_path');
    }

    public function down(): void
    {
        $this->migrator->add('invoicing.logo_path', null);
    }
};
