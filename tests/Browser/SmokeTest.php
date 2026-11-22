<?php

declare(strict_types=1);

use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('remembers the theme without cookies', function (): void {
    $page = visit('/');
    $page->click('[data-theme-toggle]')
        ->assertScript('document.documentElement.dataset.theme', 'light')
        ->assertScript('document.cookie', '');
});

it('adds a copy button to code blocks', function (): void {
    visit('/blog/a-post')->assertSee('Copy')->assertNoSmoke();
});

it('shows the 404 page with links home', function (): void {
    visit('/missing-page')->assertSee('This page doesn’t exist')->assertNoAccessibilityIssues();
});
