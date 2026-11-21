<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('profile.name', 'Chinmoy Biswas');
        $this->migrator->add('profile.title', 'WordPress & Laravel developer');
        $this->migrator->add('profile.tagline', 'I build and look after fast, maintainable websites and web apps for businesses in the UK and Europe.');
        $this->migrator->add('profile.about_md', '');
        $this->migrator->add('profile.about_html', '');
        $this->migrator->add('profile.location_public', null);
        $this->migrator->add('profile.email', null);
        $this->migrator->add('profile.socials', []);
        $this->migrator->add('profile.cv_path', null);
        $this->migrator->add('profile.avatar_path', null);

        $this->migrator->add('seo.site_title', 'Chinmoy Biswas');
        $this->migrator->add('seo.default_description', 'WordPress and Laravel developer building fast, maintainable websites and web apps for businesses in the UK and Europe.');
        $this->migrator->add('seo.default_og_image_path', null);

        $this->migrator->add('invoicing.prefix', 'INV');
        $this->migrator->add('invoicing.default_currency', 'GBP');
        $this->migrator->add('invoicing.default_due_days', 14);
        $this->migrator->add('invoicing.default_tax_rate_bp', 0);
        $this->migrator->add('invoicing.tax_note', 'VAT not applicable');
        $this->migrator->add('invoicing.footer', null);
        $this->migrator->add('invoicing.logo_path', null);

        foreach (['business_name', 'business_address', 'business_email', 'bank_details', 'wise_details', 'paypal_email'] as $key) {
            $this->migrator->addEncrypted("payment.{$key}", null);
        }
    }
};
