<?php

declare(strict_types=1);

use App\Http\Controllers\Site\PostController;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use App\Settings\ProfileSettings;
use App\Support\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ADR-0010 slice 8a: titles that fit, unique archive descriptions, thin tags kept out, complete structured data.

beforeEach(function (): void {
    config(['app.url' => 'https://example.com', 'cache.pages_enabled' => false, 'brand.area_served' => ['GB', 'EU']]);
    $profile = app(ProfileSettings::class);
    $profile->name = 'Sam Example';
    $profile->location_public = 'Sylhet, Bangladesh';
    $profile->save();
});

/** @return list<array<string, mixed>> the page's JSON-LD graph */
function graph(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return json_decode($m[1] ?? '{}', true)['@graph'] ?? [];
}

it('adds the name to a title only while it fits in 60 characters', function (): void {
    expect(Seo::title('WooCommerce checkout fix', 'Site', 'Sam Example'))->toBe('WooCommerce checkout fix · Sam Example')
        ->and(Seo::title(str_repeat('a', 50), 'Site', 'Sam Example'))->toBe(str_repeat('a', 50))
        ->and(Seo::title(null, 'Site title', 'Sam Example'))->toBe('Site title')
        ->and(mb_strlen(Seo::title(str_repeat('b', 47), 'Site', 'Sam Example')))->toBeLessThanOrEqual(Seo::TITLE_MAX);
});

it('serves en-GB pages with a short title', function (): void {
    $post = Post::factory()->for(Category::factory())->create(['seo_title' => 'WooCommerce Checkout Problem Fix: +18% More Orders']);

    $this->get(route('posts.show', $post))->assertOk()
        ->assertSee('<html lang="en-GB">', false)
        ->assertSee('<meta property="og:locale" content="en_GB">', false)
        ->assertSee('<title>WooCommerce Checkout Problem Fix: +18% More Orders</title>', false);
});

it('gives every archive its own description, and the category its intro', function (): void {
    $wordpress = Category::factory()->create(['name' => 'WordPress', 'description' => 'Fixes and case notes from real WordPress sites.']);
    $laravel = Category::factory()->create(['name' => 'Laravel', 'description' => null]);
    Post::factory()->for($wordpress)->count(2)->create();
    Post::factory()->for($laravel)->create();

    $this->get(route('posts.category', $wordpress))
        ->assertSee('<meta name="description" content="Fixes and case notes from real WordPress sites.">', false)
        ->assertSee('<p class="m-0 max-w-[560px]">Fixes and case notes from real WordPress sites.</p>', false);
    $this->get(route('posts.category', $laravel))
        ->assertSee('<meta name="description" content="'.e(PostController::archiveDescription(1, 'about Laravel')).'">', false);
});

it('keeps thin tag archives out of the index and the sitemap', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $thin = Tag::factory()->create(['name' => 'UX', 'slug' => 'ux']);
    $hub = Tag::factory()->create(['name' => 'WooCommerce', 'slug' => 'woocommerce']);
    Post::factory()->for(Category::factory())->create()->tags()->attach($thin);
    Post::factory()->for(Category::factory())->count(PostController::MIN_INDEXED_TAG_POSTS)->create()->each(fn (Post $p) => $p->tags()->attach($hub));

    $this->get(route('posts.tag', $thin))->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    $this->get(route('posts.tag', $hub))->assertOk()->assertDontSee('name="robots"', false);
    $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/tag/woocommerce')->assertDontSee('/blog/tag/ux');
});

it('leaves the category as the hub when a tag repeats its slug', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $category = Category::factory()->create(['name' => 'WordPress', 'slug' => 'wordpress']);
    $tag = Tag::factory()->create(['name' => 'WordPress', 'slug' => 'wordpress']);
    Post::factory()->for($category)->count(PostController::MIN_INDEXED_TAG_POSTS)->create()->each(fn (Post $p) => $p->tags()->attach($tag));

    $this->get(route('posts.tag', $tag))->assertSee('<meta name="robots" content="noindex, follow">', false);
    $this->get(route('posts.category', $category))->assertDontSee('name="robots"', false);
    $this->get('/sitemap.xml')->assertSee('/blog/category/wordpress')->assertDontSee('/blog/tag/wordpress');
});

it('names the author, publisher and language on posts, with the person embedded', function (): void {
    $post = Post::factory()->for(Category::factory())->create();

    $nodes = collect(graph($this->get(route('posts.show', $post))->getContent()))->keyBy('@type');

    expect($nodes['BlogPosting']['author'])->toMatchArray(['@type' => 'Person', 'name' => 'Sam Example'])
        ->and($nodes['BlogPosting']['publisher']['name'])->toBe('Sam Example')
        ->and($nodes['BlogPosting']['inLanguage'])->toBe('en-GB')
        ->and($nodes['Person']['name'])->toBe('Sam Example')
        ->and($nodes['Person']['address'])->toBe(['@type' => 'PostalAddress', 'addressLocality' => 'Sylhet', 'addressCountry' => 'Bangladesh'])
        ->and($nodes['Person']['areaServed'])->toBe(['GB', 'EU']);
});

it('describes the business and its services on the home page', function (): void {
    Service::factory()->create(['title' => 'WooCommerce development', 'summary' => 'Stores that sell.', 'is_published' => true]);

    $nodes = collect(graph($this->get('/')->getContent()))->keyBy('@type');

    expect($nodes['ProfessionalService']['hasOfferCatalog']['itemListElement'][0]['itemOffered'])
        ->toBe(['@type' => 'Service', 'name' => 'WooCommerce development', 'description' => 'Stores that sell.'])
        ->and($nodes['ProfessionalService']['founder'])->toBe(['@id' => 'https://example.com/#person']);
});

it('lists entries on the blog and projects pages as a CollectionPage', function (): void {
    $post = Post::factory()->for(Category::factory())->create(['title' => 'A post']);
    Project::factory()->caseStudy()->create(['title' => 'A case', 'slug' => 'a-case']);

    $blog = collect(graph($this->get('/blog')->getContent()))->keyBy('@type');
    $projects = collect(graph($this->get('/projects')->getContent()))->keyBy('@type');

    expect($blog['CollectionPage']['mainEntity']['itemListElement'][0])->toMatchArray(['name' => 'A post', 'url' => 'https://example.com'.route('posts.show', $post, false)])
        ->and($projects['CollectionPage']['mainEntity']['itemListElement'][0])->toMatchArray(['name' => 'A case', 'url' => 'https://example.com/projects/a-case']);
});
