<?php

declare(strict_types=1);

use App\Jobs\SubmitToIndexNow;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Settings\ProfileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ADR-0010 slice 8b: llms.txt, an explicit crawler policy in robots.txt, and IndexNow pings.

const INDEXNOW_KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

beforeEach(function (): void {
    config(['app.url' => 'https://example.com', 'cache.pages_enabled' => false]);
    Cache::flush();
});

it('names every allowed crawler in robots.txt in production, and opt-outs when configured', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['seo.disallowed_crawlers' => ['CCBot']]);

    $body = (string) $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)->toContain("User-agent: *\nAllow: /")
        ->toContain("User-agent: GPTBot\n")->toContain("User-agent: ClaudeBot\n")->toContain("User-agent: PerplexityBot\n")
        ->toContain("User-agent: CCBot\nDisallow: /")
        ->toEndWith("Sitemap: https://example.com/sitemap.xml\n");
});

it('keeps robots.txt closed outside production', function (): void {
    expect((string) $this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('serves llms.txt built from live content only', function (): void {
    $profile = app(ProfileSettings::class);
    $profile->name = 'Sam Example';
    $profile->title = 'WordPress developer';
    $profile->save();
    Service::factory()->create(['title' => 'WooCommerce [stores]', 'slug' => 'woocommerce-stores', 'summary' => 'Checkout fixes.', 'is_published' => true]);
    Project::factory()->caseStudy()->create(['title' => 'Law firm site', 'slug' => 'law-firm', 'seo_description' => 'A redesign.']);
    Post::factory()->for(Category::factory())->create(['title' => 'Live post', 'slug' => 'live', 'seo_description' => 'Fixing it.']);
    Post::factory()->draft()->create(['title' => 'Draft post']);

    $body = (string) $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

    expect($body)->toStartWith("# Sam Example\n\n> ")
        ->toContain('Sam Example — WordPress developer.')
        ->toContain("## Services\n\n- [WooCommerce \\[stores\\]](https://example.com/services/woocommerce-stores): Checkout fixes.")
        ->toContain('- [Law firm site](https://example.com/projects/law-firm): A redesign.')
        ->toContain('- [Live post](https://example.com/blog/live): Fixing it.')
        ->toContain('- [Sitemap](https://example.com/sitemap.xml)')
        ->not->toContain('Draft post');
});

it('serves the IndexNow key file only when a key is set', function (): void {
    $this->get('/indexnow.txt')->assertNotFound();

    config(['seo.indexnow_key' => INDEXNOW_KEY]);
    $this->get('/indexnow.txt')->assertOk()->assertSeeText(INDEXNOW_KEY);
});

it('queues one IndexNow ping when a live post is saved in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['seo.indexnow_key' => INDEXNOW_KEY]);
    Queue::fake();

    $post = Post::factory()->for(Category::factory())->create(['slug' => 'fresh']);
    $post->update(['title' => 'Edited again']); // within the 10-minute window: no second ping

    Queue::assertPushed(SubmitToIndexNow::class, 1);
    Queue::assertPushed(SubmitToIndexNow::class, fn (SubmitToIndexNow $job): bool => $job->urls === ['https://example.com/blog/fresh', 'https://example.com/blog']);
});

it('does not ping for drafts, without a key, or outside production', function (string $env, ?string $key, bool $draft): void {
    app()->detectEnvironment(fn (): string => $env);
    config(['seo.indexnow_key' => $key]);
    Queue::fake();

    $draft ? Post::factory()->draft()->create() : Post::factory()->for(Category::factory())->create();

    Queue::assertNotPushed(SubmitToIndexNow::class);
})->with([
    'draft' => ['production', INDEXNOW_KEY, true],
    'no key' => ['production', null, false],
    'local' => ['local', INDEXNOW_KEY, false],
]);

it('sends the host, key, key location and URLs to IndexNow', function (): void {
    config(['seo.indexnow_key' => INDEXNOW_KEY]);
    Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);

    (new SubmitToIndexNow(['https://example.com/blog/a', 'https://example.com/blog/a']))->handle();

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.indexnow.org/indexnow' && $r->data() === [
        'host' => 'example.com',
        'key' => INDEXNOW_KEY,
        'keyLocation' => 'https://example.com/indexnow.txt',
        'urlList' => ['https://example.com/blog/a'],
    ]);
});

it('logs a rejected submission instead of retrying, and retries a server error', function (): void {
    config(['seo.indexnow_key' => INDEXNOW_KEY]);

    Http::fake(['api.indexnow.org/*' => Http::sequence()->push('', 422)->push('', 503)]);
    (new SubmitToIndexNow(['https://example.com/']))->handle();

    expect(fn () => (new SubmitToIndexNow(['https://example.com/']))->handle())->toThrow(RequestException::class);
});
