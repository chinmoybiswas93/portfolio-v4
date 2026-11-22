<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Design-fidelity audit helper (slice 3.7): screenshots of every admin screen at desktop and mobile widths.
 * Runs only with AUDIT_SCREENSHOTS=1; images go to tests/Browser/Screenshots (git-ignored).
 */
it('captures every admin screen for the design audit', function (): void {
    $this->actingAs(adminWithMfa());
    $project = Project::factory()->caseStudy()->create(['title' => 'TuFlamenco']);
    $post = Post::factory()->for(Category::factory())->create();
    $client = Client::factory()->create(['company_name' => 'Acme Ltd', 'country_code' => 'GB']);
    $invoice = Invoice::factory()->for($client)->create();
    $invoice->items()->create(['position' => 0, 'description' => 'Website care plan', 'quantity_milli' => 1000, 'unit_price_minor' => 35000]);
    app(RecalculateInvoice::class)($invoice);
    $issued = Invoice::factory()->for($client)->create();
    $issued->items()->create(['position' => 0, 'description' => 'Plugin work', 'quantity_milli' => 2500, 'unit_price_minor' => 4500]);
    app(IssueInvoice::class)($issued);

    $screens = [
        'dashboard' => '/admin', 'projects' => '/admin/projects', 'project-edit' => "/admin/projects/{$project->id}/edit",
        'posts' => '/admin/posts', 'post-edit' => "/admin/posts/{$post->id}/edit", 'services' => '/admin/services',
        'experiences' => '/admin/experiences', 'categories' => '/admin/categories', 'tags' => '/admin/tags',
        'redirects' => '/admin/redirects', 'clients' => '/admin/clients',
        'invoices' => '/admin/invoices', 'invoice-draft' => "/admin/invoices/{$invoice->id}/edit", 'invoice-issued' => "/admin/invoices/{$issued->id}/edit",
        'settings-profile' => '/admin/settings/profile', 'settings-seo' => '/admin/settings/seo', 'settings-invoicing' => '/admin/settings/invoicing',
        'settings-payment' => '/admin/settings/payment', 'account' => '/admin/profile',
    ];
    foreach ($screens as $name => $url) {
        visit($url)->resize(1440, 900)->screenshot(fullPage: true, filename: "admin-{$name}-desktop");
        visit($url)->on()->mobile()->screenshot(fullPage: true, filename: "admin-{$name}-mobile");
    }
    visit('/admin/categories')->resize(1440, 900)->click('New category')->wait(1)->screenshot(filename: 'admin-category-modal-desktop');
    expect(true)->toBeTrue();
})->skip(fn (): bool => ! env('AUDIT_SCREENSHOTS'), 'Audit screenshots run only with AUDIT_SCREENSHOTS=1');

it('captures the public post and case study screens', function (): void {
    Storage::fake('public');
    $tag = Tag::findOrCreateByName('Performance');
    $post = Post::factory()->for(Category::factory()->create(['name' => 'WordPress']))->create([
        'slug' => 'speed', 'title' => 'Speeding up WordPress on shared hosting',
        'body_md' => "Intro paragraph about a slow site.\n\n## Measure first\n\nUse real numbers.\n\n### Field data\n\nCrUX and logs.\n\n## Fix the biggest cost\n\nImages and fonts.\n\n## Keep it fast\n\nBudgets in CI.",
    ]);
    $post->tags()->attach($tag);
    foreach (['Caching on Hostinger', 'Fonts without layout shift'] as $title) {
        Post::factory()->create(['title' => $title])->tags()->attach($tag);
    }
    $project = Project::factory()->caseStudy()->create(['slug' => 'shop', 'title' => 'TuFlamenco', 'live_url' => 'https://tuflamenco.com']);
    foreach ([1, 2, 3] as $n) {
        $project->addMedia(UploadedFile::fake()->image("g{$n}.jpg", 1200, 750))->withCustomProperties(['alt' => "Screen {$n}"])->toMediaCollection('gallery');
    }

    visit('/blog/speed')->resize(1440, 900)->screenshot(fullPage: true, filename: 'public-post-desktop');
    visit('/blog/speed')->on()->mobile()->screenshot(fullPage: true, filename: 'public-post-mobile');
    visit('/projects/shop')->resize(1440, 900)->screenshot(fullPage: true, filename: 'public-case-study-desktop');
    visit('/projects/shop')->resize(1440, 900)->click('[data-lightbox-open="1"]')->screenshot(filename: 'public-lightbox-desktop');
    expect(true)->toBeTrue();
})->skip(fn (): bool => ! env('AUDIT_SCREENSHOTS'), 'Audit screenshots run only with AUDIT_SCREENSHOTS=1');
