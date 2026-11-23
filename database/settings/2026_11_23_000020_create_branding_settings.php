<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/*
 * Additive and backward-compatible: nothing is overwritten. Empty paths mean "use the bundled file".
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['logo_path', 'favicon_path', 'apple_touch_path', 'og_logo_path'] as $key) {
            $this->migrator->add("branding.{$key}", null);
        }
    }
};
