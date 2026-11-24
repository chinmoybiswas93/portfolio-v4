<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Client;
use App\Models\Experience;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * ADR-0010: in dark mode every text colour reaches WCAG AAA (7:1, or 4.5:1 for large text) on the public site
 * and in admin. axe's `color-contrast-enhanced` rule checks it; the QA test only enforces AA.
 */

/** @return list<string> one line per failing element */
function darkContrastViolations(mixed $page): array
{
    $axe = (string) file_get_contents(base_path('node_modules/axe-core/axe.min.js'));

    return $page->script($axe.<<<'JS'
        ;(async () => {
            const result = await axe.run(document, { runOnly: ['color-contrast-enhanced'] });
            return result.violations.flatMap((v) => v.nodes.map((n) => {
                const d = (n.any[0] || {}).data || {};
                return `${d.contrastRatio}:1 ${d.fgColor} on ${d.bgColor} — ${n.target.join(' ')}`;
            }));
        })()
        JS);
}

beforeEach(function (): void {
    Service::factory()->count(2)->create();
    Service::factory()->create(['title' => 'WooCommerce development', 'slug' => 'svc', 'is_published' => true, 'faq' => [['question' => 'Existing store?', 'answer' => 'Yes.']], 'cta_line' => 'Have a store in mind?']);
    Experience::factory()->current()->create();
    Project::factory()->caseStudy()->create(['title' => 'Case One', 'slug' => 'case-one', 'is_featured' => true]);
    Post::factory()->for(Category::factory())->create(['title' => 'A post', 'slug' => 'a-post', 'published_at' => now()->subDays(10), 'reviewed_at' => now()->subDay(), 'takeaways' => ['Measure first', 'Fix the slowest step'], 'faq' => [['question' => 'Is caching enough?', 'answer' => 'Rarely on its own.']], 'body_md' => "## Heading\n\nText with a [link](https://example.com).\n\n> A quote\n\n```php\necho 'hi';\n```"]);
});

it('meets AAA contrast in dark mode on public pages', function (string $url): void {
    expect(darkContrastViolations(visit($url)->inDarkMode()->wait(0.3)))->toBe([]);
})->with(['/', '/services', '/services/svc', '/projects', '/projects/case-one', '/blog', '/blog/a-post']);

it('meets AAA contrast in dark mode on admin screens', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(adminWithMfa());
    Invoice::factory()->for(Client::factory()->create(['company_name' => 'Acme Ltd']))->create();
    $post = Post::query()->firstOrFail();
    $project = Project::query()->firstOrFail();

    $screens = [
        adminPath(), adminPath('/posts'), adminPath("/posts/{$post->id}/edit"), adminPath("/projects/{$project->id}/edit"),
        adminPath('/clients'), adminPath('/invoices'), adminPath('/settings/profile'), adminPath('/profile'),
    ];

    $violations = [];
    foreach ($screens as $url) {
        // Admin screens fade in for 160 ms; check once the fade is done.
        foreach (darkContrastViolations(visit($url)->inDarkMode()->wait(0.5)) as $line) {
            $violations[] = "{$url}: {$line}";
        }
    }

    expect($violations)->toBe([]);
});
