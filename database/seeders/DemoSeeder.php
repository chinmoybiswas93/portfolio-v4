<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Fictional demo content for local trials and Lighthouse CI (docs/TESTING.md §6): a case study and a post with
 * real cover images, so image delivery and LCP are measured. Never run it in production. Run with QUEUE_CONNECTION=sync so image sizes are made at once.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ContentSeeder::class);

        Experience::factory()->current()->create();
        Experience::factory()->count(3)->create();

        $caseStudy = Project::factory()->caseStudy()->create([
            'title' => 'Sample Shop',
            'slug' => 'sample-shop',
            'is_featured' => true,
            'summary' => 'A fictional online shop used to demo case studies, with online booking and payments.',
        ]);
        $caseStudy->addMediaFromString(self::photo(1600, 1000))->usingFileName('sample-shop.jpg')->toMediaCollection('cover');
        Project::factory()->count(5)->create(['is_featured' => true]);

        $category = Category::query()->firstOrFail();
        $post = Post::factory()->for($category)->create([
            'title' => 'Speeding up WordPress on shared hosting',
            'slug' => 'speeding-up-wordpress',
            'body_md' => str_repeat("A practical paragraph about caching, images and fonts on a small server.\n\n", 12)
                ."```php\nadd_filter('wp_lazy_loading_enabled', '__return_true');\n```\n",
        ]);
        $post->addMediaFromString(self::photo(1600, 1000))->usingFileName('cover.jpg')->toMediaCollection('cover');
        Post::factory()->count(6)->for($category)->create();
    }

    /** A soft gradient JPEG standing in for a real photo. */
    private static function photo(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            $shade = (int) (40 + 120 * $y / $height);
            imageline($image, 0, $y, $width, $y, (int) imagecolorallocate($image, 15, $shade, 120 + (int) ($shade / 2)));
        }
        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }
}
