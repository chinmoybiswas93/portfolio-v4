<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\McpAuditLog;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

/*
 * Design-fidelity audit helper (slice 3.7): screenshots of every admin screen at desktop and mobile widths.
 * Runs only with AUDIT_SCREENSHOTS=1; images go to tests/Browser/Screenshots (git-ignored).
 */
it('captures every admin screen for the design audit', function (): void {
    $this->actingAs(adminWithMfa());
    $project = Project::factory()->caseStudy()->create(['title' => 'Sample Shop']);
    $post = Post::factory()->for(Category::factory())->create();
    $client = Client::factory()->create(['company_name' => 'Acme Ltd', 'country_code' => 'GB']);
    $invoice = Invoice::factory()->for($client)->create();
    $invoice->items()->create(['position' => 0, 'description' => 'Website care plan', 'quantity_milli' => 1000, 'unit_price_minor' => 35000]);
    app(RecalculateInvoice::class)($invoice);
    $issued = Invoice::factory()->for($client)->create();
    $issued->items()->create(['position' => 0, 'description' => 'Plugin work', 'quantity_milli' => 2500, 'unit_price_minor' => 4500]);
    app(IssueInvoice::class)($issued);

    $screens = [
        'dashboard' => adminPath(), 'projects' => adminPath('/projects'), 'project-edit' => adminPath("/projects/{$project->id}/edit"),
        'posts' => adminPath('/posts'), 'post-edit' => adminPath("/posts/{$post->id}/edit"), 'services' => adminPath('/services'),
        'experiences' => adminPath('/experiences'), 'categories' => adminPath('/categories'), 'tags' => adminPath('/tags'),
        'redirects' => adminPath('/redirects'), 'clients' => adminPath('/clients'),
        'invoices' => adminPath('/invoices'), 'invoice-draft' => adminPath("/invoices/{$invoice->id}/edit"), 'invoice-issued' => adminPath("/invoices/{$issued->id}/edit"),
        'settings-profile' => adminPath('/settings/profile'), 'settings-seo' => adminPath('/settings/seo'), 'settings-invoicing' => adminPath('/settings/invoicing'),
        'settings-payment' => adminPath('/settings/payment'), 'account' => adminPath('/profile'),
    ];
    foreach ($screens as $name => $url) {
        visit($url)->resize(1440, 900)->screenshot(fullPage: true, filename: "admin-{$name}-desktop");
        visit($url)->on()->mobile()->screenshot(fullPage: true, filename: "admin-{$name}-mobile");
    }
    visit(adminPath('/categories'))->resize(1440, 900)->click('New category')->wait(1)->screenshot(filename: 'admin-category-modal-desktop');
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
    $project = Project::factory()->caseStudy()->create(['slug' => 'shop', 'title' => 'Sample Shop', 'live_url' => 'https://sample-shop.example']);
    foreach ([1, 2, 3] as $n) {
        $project->addMedia(UploadedFile::fake()->image("g{$n}.jpg", 1200, 750))->withCustomProperties(['alt' => "Screen {$n}"])->toMediaCollection('gallery');
    }

    visit('/blog/speed')->resize(1440, 900)->screenshot(fullPage: true, filename: 'public-post-desktop');
    visit('/blog/speed')->on()->mobile()->screenshot(fullPage: true, filename: 'public-post-mobile');
    visit('/projects/shop')->resize(1440, 900)->screenshot(fullPage: true, filename: 'public-case-study-desktop');
    visit('/projects/shop')->resize(1440, 900)->click('[data-lightbox-open="1"]')->screenshot(filename: 'public-lightbox-desktop');
    expect(true)->toBeTrue();
})->skip(fn (): bool => ! env('AUDIT_SCREENSHOTS'), 'Audit screenshots run only with AUDIT_SCREENSHOTS=1');

it('captures the MCP admin screens', function (): void {
    $this->actingAs(adminWithMfa());
    Passport::client()->newQuery()->forceCreate(['name' => 'Claude Code', 'secret' => null, 'provider' => null, 'redirect_uris' => ['http://localhost:53682/callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'scopes' => ['mcp:use', 'content:read', 'content:write', 'media:upload']]);
    Passport::client()->newQuery()->forceCreate(['name' => 'Claude.ai', 'secret' => null, 'provider' => null, 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'], 'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false, 'scopes' => null]);
    foreach ([['save-content', 'post', '12', 'Speeding up WordPress', ['action' => 'updated', 'fields' => ['title' => [], 'body_md' => []]], 'ok', 200], ['trash-content', 'post', '9', 'Old draft', ['action' => 'trashed'], 'ok', 200], ['list-invoices', null, null, null, null, 'denied', 403]] as [$tool, $type, $id, $label, $changes, $result, $status]) {
        McpAuditLog::query()->create(['method' => 'tools/call', 'client_id' => 'x', 'client_name' => 'Claude Code', 'tool' => $tool, 'target_type' => $type, 'target_id' => $id, 'target_label' => $label, 'changes' => $changes, 'result' => $result, 'http_status' => $status, 'error' => $result === 'denied' ? 'insufficient_scope' : null, 'ip' => '203.0.113.24']);
    }

    foreach (['settings-mcp' => '/settings/mcp', 'mcp-apps' => '/mcp/apps', 'mcp-audit' => '/mcp/audit'] as $name => $path) {
        visit(adminPath($path))->resize(1440, 900)->screenshot(fullPage: true, filename: "admin-{$name}-desktop");
    }
    expect(true)->toBeTrue();
})->skip(fn (): bool => ! env('AUDIT_SCREENSHOTS'), 'Audit screenshots run only with AUDIT_SCREENSHOTS=1');
