<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Category;
use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Tag;
use App\Settings\ProfileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

it('renders the home page from published content only', function (): void {
    Service::factory()->create(['title' => 'Speed audits']);
    Service::factory()->create(['title' => 'Hidden service', 'is_published' => false]);
    Experience::factory()->current()->create(['position' => 'Support Engineer']);
    Project::factory()->create(['title' => 'Featured One', 'slug' => 'featured-one', 'is_featured' => true]);
    Project::factory()->draft()->create(['title' => 'Draft Project', 'slug' => 'draft-project', 'is_featured' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee(app(ProfileSettings::class)->name)
        ->assertSee('Speed audits')->assertDontSee('Hidden service')
        ->assertSee('Support Engineer')->assertSee('Now')
        ->assertSee('Featured One')->assertDontSee('Draft Project');
});

it('falls back to the hello@ address and never shows a missing section', function (): void {
    $this->get('/')
        ->assertSee('hello@chinmoybiswas.com')
        ->assertDontSee('id="writing"', false)
        ->assertDontSee('id="services"', false);
});

it('serves public pages without cookies and with a strict CSP', function (string $path): void {
    $response = $this->get($path)->assertOk();

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->get('Content-Security-Policy'))->toBe(SecurityHeaders::PUBLIC_CSP)->not->toContain('unsafe')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
})->with(['/', '/projects', '/blog']);

it('never sends HSTS with includeSubDomains', function (): void {
    $hsts = (string) $this->get('https://localhost/')->headers->get('Strict-Transport-Security');

    expect($hsts)->toBe('max-age=31536000');
});

it('lists projects newest first and only shows case studies that exist', function (): void {
    Project::factory()->create(['title' => 'Old', 'slug' => 'old', 'year' => 2020]);
    Project::factory()->caseStudy()->create(['title' => 'New', 'slug' => 'new', 'year' => 2026]);
    Project::factory()->draft()->create(['title' => 'Secret', 'slug' => 'secret']);

    $this->get('/projects')->assertOk()->assertSeeInOrder(['New', 'Old'])->assertDontSee('Secret');
    $this->get('/projects/new')->assertOk()->assertSee('The problem');
    $this->get('/projects/old')->assertNotFound();
    $this->get('/projects/secret')->assertNotFound();
});

it('shows published posts, escapes HTML in posts and hides drafts and future posts', function (): void {
    $category = Category::factory()->create(['name' => 'WordPress', 'slug' => 'wordpress']);
    $post = Post::factory()->for($category)->create(['title' => 'Live post', 'slug' => 'live-post', 'body_md' => "Hello <script>alert(1)</script>\n\n```php\necho 1;\n```"]);
    Post::factory()->draft()->create(['slug' => 'draft-post']);
    Post::factory()->create(['slug' => 'future-post', 'status' => PostStatus::Scheduled, 'published_at' => now()->addWeek()]);

    $this->get('/blog')->assertOk()->assertSee('Live post');
    $this->get('/blog/category/wordpress')->assertOk()->assertSee('Live post');
    $this->get('/blog/live-post')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('data-lang="php"', false);
    $this->get('/blog/draft-post')->assertNotFound();
    $this->get('/blog/future-post')->assertNotFound();

    $topic = Tag::findOrCreateByName('Plugins');
    $post->tags()->attach($topic);
    $this->get('/blog/tag/plugins')->assertOk()->assertSee('Live post');
    Tag::findOrCreateByName('Laravel');
    $this->get('/blog/tag/laravel')->assertNotFound();
});

it('paginates the writing list', function (): void {
    Post::factory()->count(12)->create();

    $this->get('/blog')->assertOk()->assertSee('Page 1 of 2');
    $this->get('/blog?page=2')->assertOk()->assertSee('Page 2 of 2');
});

it('streams the CV only when one is uploaded', function (): void {
    Storage::fake('local');
    $this->get('/cv')->assertNotFound();

    Storage::disk('local')->put('cv/resume.pdf', '%PDF-1.4');
    $profile = app(ProfileSettings::class);
    $profile->cv_path = 'cv/resume.pdf';
    $profile->save();

    $this->get('/cv')->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('follows admin redirects and counts hits, otherwise shows the 404 page', function (): void {
    Redirect::factory()->create(['from_path' => '/old-page', 'to_path' => '/projects', 'status_code' => 301]);

    $this->get('/old-page')->assertRedirect('/projects')->assertStatus(301);
    expect(Redirect::query()->value('hits'))->toBe(1);

    $this->get('/does-not-exist')->assertNotFound()->assertSee('This page doesn’t exist');
});

it('caches public pages and clears the cache when content changes', function (): void {
    config(['cache.pages_enabled' => true, 'cache.stores.pages' => ['driver' => 'array']]);
    $service = Service::factory()->create(['title' => 'First title']);

    $this->get('/')->assertHeader('X-Page-Cache', 'MISS')->assertSee('First title');
    $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

    $service->update(['title' => 'Second title']);
    $this->get('/')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Second title');
});

it('does not cache admin or error pages', function (): void {
    config(['cache.pages_enabled' => true, 'cache.stores.pages' => ['driver' => 'array']]);

    $this->get('/does-not-exist')->assertNotFound()->assertHeaderMissing('X-Page-Cache');
    $this->get('/admin/login')->assertHeaderMissing('X-Page-Cache');
});

it('never caches the empty body of a HEAD request', function (): void {
    config(['cache.pages_enabled' => true, 'cache.stores.pages' => ['driver' => 'array']]);
    Service::factory()->create(['title' => 'Visible service']);

    $this->call('HEAD', '/')->assertOk();
    $this->get('/')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Visible service');
});
