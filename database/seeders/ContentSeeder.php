<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Approved starter content every environment needs. Idempotent and create-only.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['wordpress-themes-and-plugins', 'WordPress themes and plugins', 'Custom block themes, Gutenberg blocks and plugins, written to WordPress coding standards and easy for your team to edit.'],
            ['web-apps-and-integrations', 'Web apps and integrations', 'Laravel and JavaScript apps, admin panels, REST APIs and connections to the tools your business already uses.'],
            ['care-and-support', 'Care and support', 'Updates, fixes, speed and security checks for live WordPress sites, handled by someone who does support every day.'],
        ];
        foreach ($services as $i => [$slug, $title, $summary]) {
            // Create only: never overwrite text the owner has edited in admin.
            Service::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'summary' => $summary,
                'sort_order' => $i,
                'is_published' => true,
            ]);
        }

        foreach (['WordPress', 'Laravel', 'Support'] as $name) {
            Category::query()->firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name]);
        }
    }
}
