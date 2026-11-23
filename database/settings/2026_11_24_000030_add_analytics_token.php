<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Cloudflare Web Analytics beacon token (ADR-0010). Public by design: it is printed in every page. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.analytics_token', null);
    }
};
