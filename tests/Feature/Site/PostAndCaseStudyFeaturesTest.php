<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use App\Support\Markdown\TableOfContents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('builds contents from h2 and h3 with unique ids', function (): void {
    $toc = TableOfContents::build('<h2>Start here</h2><p>x</p><h3>Detail &amp; more</h3><h2>Start here</h2><h4>Skip</h4>');

    expect($toc['items'])->toBe([
        ['level' => 2, 'id' => 'start-here', 'text' => 'Start here'],
        ['level' => 3, 'id' => 'detail-more', 'text' => 'Detail & more'],
        ['level' => 2, 'id' => 'start-here-2', 'text' => 'Start here'],
    ])->and($toc['html'])->toContain('<h2 id="start-here">')->toContain('<h2 id="start-here-2">')->toContain('<h4>Skip</h4>');
});

it('shows contents, breadcrumb, share links, related posts and a way back on a post', function (): void {
    $category = Category::factory()->create(['name' => 'WordPress', 'slug' => 'wordpress']);
    $tag = Tag::findOrCreateByName('Speed');
    $post = Post::factory()->for($category)->create(['slug' => 'speed', 'title' => 'Speed tips', 'body_md' => "## First part\n\nText\n\n### A detail\n\n## Second part"]);
    $post->tags()->attach($tag);
    $related = Post::factory()->create(['title' => 'Caching guide', 'published_at' => now()->subDay()]);
    $related->tags()->attach($tag);
    Post::factory()->draft()->create(['title' => 'Hidden draft'])->tags()->attach($tag);

    $this->get('/blog/speed')->assertOk()
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('On this page')
        ->assertSee('href="#first-part"', false)->assertSee('<h2 id="first-part">', false)->assertSee('<h3 id="a-detail">', false)
        ->assertSee('https://www.linkedin.com/sharing/share-offsite/?url=', false)
        ->assertSee('https://x.com/intent/post?url=', false)
        ->assertSee('https://www.facebook.com/sharer/sharer.php?u=', false)
        ->assertSee('data-copy-link', false)
        ->assertSee('Related posts')->assertSee('Caching guide')->assertDontSee('Hidden draft')
        ->assertSee('Back to writing');
});

it('leaves out the contents when a post has fewer than two headings', function (): void {
    Post::factory()->create(['slug' => 'short', 'body_md' => "Just text.\n\n## Only one"]);

    $this->get('/blog/short')->assertOk()->assertDontSee('On this page');
});

it('puts the live and source links next to the stack on a case study', function (): void {
    Project::factory()->caseStudy()->create(['slug' => 'shop', 'live_url' => 'https://shop.example.com', 'repo_url' => 'https://github.com/example/shop']);

    $html = (string) $this->get('/projects/shop')->assertOk()->getContent();
    $row = str($html)->after('class="cs-tags-row')->before('</div>')->toString();

    expect($row)->toContain('Visit shop.example.com')->toContain('Source code')
        ->and(substr_count($html, 'Visit shop.example.com'))->toBe(1);
});

it('opens gallery images in a lightbox', function (): void {
    Storage::fake('public');
    $project = Project::factory()->caseStudy()->create(['slug' => 'gallery']);
    foreach ([1, 2] as $n) {
        $project->addMedia(UploadedFile::fake()->image("g{$n}.jpg", 800, 500))->withCustomProperties(['alt' => "Screen {$n}"])->toMediaCollection('gallery');
    }

    $this->get('/projects/gallery')->assertOk()
        ->assertSee('data-lightbox-open="0"', false)->assertSee('data-lightbox-open="1"', false)
        ->assertSee('Open image 2 of 2: Screen 2', false)
        ->assertSee('<dialog class="lightbox"', false)->assertSee('data-lightbox-next', false);
});
