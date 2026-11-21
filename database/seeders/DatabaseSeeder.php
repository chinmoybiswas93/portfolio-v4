<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ContentSeeder::class);

        // Sample posts for local development only; real content comes from the admin.
        if (app()->environment('local')) {
            Category::query()->each(function (Category $category): void {
                Post::factory()->count(2)->for($category)->create();
            });
        }
    }
}
