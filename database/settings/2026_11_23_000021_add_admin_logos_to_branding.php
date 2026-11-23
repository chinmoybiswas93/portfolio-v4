<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/*
 * Additive: two optional admin header logos (light and dark theme). Empty means "use the bundled file".
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('branding.admin_logo_path', null);
        $this->migrator->add('branding.admin_logo_dark_path', null);
    }
};
