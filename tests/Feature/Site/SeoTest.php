<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use App\Support\Seo\OgImage;
use App\Support\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.url' => 'https://example.com']));

/**
 * @return array<string, mixed>
 */
function jsonLdGraph(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);

    return json_decode($m[1] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
}

it('sets canonical, Open Graph and Twitter tags on every page', function (): void {
    Post::factory()->count(11)->create(); // page 2 must exist
    $html = $this->get('/blog?page=2&utm_source=x')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="https://example.com/blog?page=2">')
        ->toContain('<meta property="og:url" content="https://example.com/blog?page=2">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta property="og:image" content="https://example.com/storage/og/')
        ->toContain('rel="alternate" type="application/rss+xml"');
});

it('keeps the canonical free of page=1 and other query strings', function (): void {
    expect($this->get('/projects?page=1&ref=abc')->getContent())->toContain('<link rel="canonical" href="https://example.com/projects">');
});

it('describes the home page as a Person, WebSite and ProfessionalService', function (): void {
    $types = collect(jsonLdGraph((string) $this->get('/')->getContent())['@graph'])->pluck('@type')->all();

    expect($types)->toBe(['Person', 'WebSite', 'ProfessionalService']);
});

it('describes a post as a BlogPosting with breadcrumbs and article times', function (): void {
    $post = Post::factory()->for(Category::factory())->create(['title' => 'Speed </script> tips', 'slug' => 'speed']);

    $html = (string) $this->get('/blog/speed')->assertOk()->getContent();
    $graph = jsonLdGraph($html)['@graph'];

    expect($graph[0]['@type'])->toBe('BlogPosting')
        ->and($graph[0]['headline'])->toBe('Speed </script> tips')
        ->and($graph[0]['datePublished'])->toBe($post->published_at?->toAtomString())
        ->and($graph[1]['@type'])->toBe('BreadcrumbList')
        ->and($graph[1]['itemListElement'][2]['item'])->toBe('https://example.com/blog/speed')
        ->and($html)->toContain('<meta property="og:type" content="article">')
        ->toContain('article:published_time');
});

it('describes a case study as a CreativeWork', function (): void {
    Project::factory()->caseStudy()->create(['slug' => 'shop']);

    $graph = jsonLdGraph((string) $this->get('/projects/shop')->getContent())['@graph'];

    expect($graph[0]['@type'])->toBe('CreativeWork')->and($graph[1]['@type'])->toBe('BreadcrumbList');
});

it('generates the share card once and reuses it', function (): void {
    Project::factory()->caseStudy()->create(['slug' => 'shop', 'title' => 'Shop']);

    $this->get('/projects/shop');
    $path = OgImage::path('Case study', 'Shop');
    Storage::disk('public')->assertExists($path);

    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get($path)) ?: [0, 0];
    expect([$width, $height])->toBe([1200, 630])
        ->and(OgImage::path('Case study', 'Shop'))->toBe($path)
        ->and(OgImage::path('Case study', 'Shop 2'))->not->toBe($path);
});

it('lists only published, public URLs in the sitemap', function (): void {
    $category = Category::factory()->create(['slug' => 'wordpress']);
    $topic = Tag::factory()->create(['slug' => 'speed']);
    Post::factory()->for($category)->create(['slug' => 'live'])->tags()->attach($topic);
    Post::factory()->draft()->create(['slug' => 'draft']);
    Project::factory()->caseStudy()->create(['slug' => 'case']);
    Project::factory()->create(['slug' => 'no-case-study']);
    Project::factory()->draft()->caseStudy()->create(['slug' => 'draft-case']);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string((string) $response->getContent());
    $locs = [];
    foreach ($xml->url ?? [] as $url) {
        $locs[] = (string) $url->loc;
    }

    expect($locs)->toEqualCanonicalizing([
        'https://example.com/', 'https://example.com/projects', 'https://example.com/blog',
        'https://example.com/projects/case', 'https://example.com/blog/live',
        // The one-post tag archive is thin (noindex, ADR-0010), so it is not listed.
        'https://example.com/blog/category/wordpress',
    ]);
});

it('serves a valid RSS feed of published posts with full content', function (): void {
    Post::factory()->create(['slug' => 'live', 'title' => 'Live & well', 'body_md' => "Body with ]]> inside\n\n**bold**"]);
    Post::factory()->draft()->create(['slug' => 'draft']);

    $response = $this->get('/feed.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse()
        ->and(count($xml->channel->item))->toBe(1)
        ->and((string) $xml->channel->item[0]->title)->toBe('Live & well')
        ->and((string) $xml->channel->item[0]->children('content', true)->encoded)->toContain('<strong>bold</strong>')->toContain(']]&gt;');
});

it('blocks indexing outside production', function (): void {
    expect(Seo::indexable())->toBeFalse();

    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('allows indexing in production and points crawlers to the sitemap', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://example.com/sitemap.xml', false)->assertDontSee("Disallow: /\n", false);
    $this->get('/')->assertHeaderMissing('X-Robots-Tag')->assertDontSee('name="robots"', false);
});

it('caches the sitemap like a page and keeps admin out of the LiteSpeed cache', function (): void {
    // Own in-memory store: parallel test processes share the file store and flush it on every model save.
    config(['cache.pages_enabled' => true, 'cache.stores.pages' => ['driver' => 'array']]);
    app('cache')->forgetDriver('pages');
    $this->get('/sitemap.xml')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/sitemap.xml')->assertHeader('X-Page-Cache', 'HIT');
    $this->get(adminPath('/login'))->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    $this->get('/up')->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
});
