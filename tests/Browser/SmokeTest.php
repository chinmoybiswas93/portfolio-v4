<?php

declare(strict_types=1);

use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Service::factory()->count(2)->create();
    Experience::factory()->current()->create();
    Project::factory()->caseStudy()->create(['title' => 'Case One', 'slug' => 'case-one', 'is_featured' => true]);
    Post::factory()->create(['title' => 'A post', 'slug' => 'a-post', 'body_md' => "## Heading\n\n```php\necho 1;\n```"]);
});

it('loads every public page without errors or accessibility issues', function (): void {
    $pages = visit(['/', '/projects', '/projects/case-one', '/blog', '/blog/a-post']);

    $pages->assertNoSmoke()->assertNoAccessibilityIssues();
});

it('toggles the theme with one button and remembers it without cookies', function (): void {
    // The test browser prefers light, so the first visit is light and shows the moon.
    $page = visit('/');
    $page->assertScript('document.documentElement.dataset.theme', 'light')
        ->assertScript("localStorage.getItem('theme')", null)
        ->click('[data-theme-toggle]')
        ->assertScript('document.documentElement.dataset.theme ?? null', null)
        ->assertScript("localStorage.getItem('theme')", 'dark')
        ->click('[data-theme-toggle]')
        ->assertScript("localStorage.getItem('theme')", 'light')
        ->assertScript('document.cookie', '');
});

it('adds a copy button to code blocks', function (): void {
    visit('/blog/a-post')->assertSee('Copy')->assertNoSmoke();
});

it('shows the 404 page with links home', function (): void {
    visit('/missing-page')->assertSee('This page doesn’t exist')->assertNoAccessibilityIssues();
});

it('opens the case study gallery in a lightbox and moves with the arrow keys', function (): void {
    Storage::fake('public');
    $project = Project::query()->where('slug', 'case-one')->firstOrFail();
    foreach ([1, 2] as $n) {
        $project->addMedia(UploadedFile::fake()->image("g{$n}.jpg", 800, 500))->withCustomProperties(['alt' => "Screen {$n}"])->toMediaCollection('gallery');
    }

    visit('/projects/case-one')
        ->click('[data-lightbox-open="0"]')
        ->assertScript('document.querySelector("[data-lightbox]").open', true)
        ->assertSeeIn('[data-lightbox-count]', '1 / 2')
        ->keys('[data-lightbox]', 'ArrowRight')
        ->assertSeeIn('[data-lightbox-count]', '2 / 2')
        ->keys('[data-lightbox]', 'Escape')
        ->assertScript('document.querySelector("[data-lightbox]").open', false);
});
