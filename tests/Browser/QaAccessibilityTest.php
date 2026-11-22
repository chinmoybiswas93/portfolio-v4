<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Models\Category;
use App\Models\Client;
use App\Models\Experience;
use App\Models\Invoice;
use App\Models\McpAuditLog;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Phase 4 accessibility pass: axe at the strictest level (every impact, down to "minor") on every public
 * page in both themes and on mobile, and on every admin screen. Level 3 = critical, serious, moderate and minor.
 */

beforeEach(function (): void {
    Service::factory()->count(2)->create();
    Experience::factory()->current()->create();
    Project::factory()->caseStudy()->create(['title' => 'Case One', 'slug' => 'case-one', 'is_featured' => true]);
    Post::factory()->for(Category::factory())->create(['title' => 'A post', 'slug' => 'a-post', 'body_md' => "## Heading\n\nText with a [link](https://example.com).\n\n```php\necho 1;\n```"]);
});

const QA_PUBLIC_PAGES = ['/', '/projects', '/projects/case-one', '/blog', '/blog/a-post', '/missing-page'];

it('has no accessibility issues on public pages (light, desktop)', function (string $url): void {
    visit($url)->assertNoAccessibilityIssues(3);
})->with(QA_PUBLIC_PAGES);

it('has no accessibility issues on public pages (dark)', function (string $url): void {
    visit($url)->inDarkMode()->assertNoAccessibilityIssues(3);
})->with(QA_PUBLIC_PAGES);

it('has no accessibility issues on public pages (mobile)', function (string $url): void {
    visit($url)->on()->mobile()->assertNoAccessibilityIssues(3);
})->with(QA_PUBLIC_PAGES);

it('has no accessibility issues on admin screens', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(adminWithMfa());
    $project = Project::query()->firstOrFail();
    $post = Post::query()->firstOrFail();
    $client = Client::factory()->create(['company_name' => 'Acme Ltd', 'country_code' => 'GB']);
    $draft = Invoice::factory()->for($client)->create();
    $draft->items()->create(['position' => 0, 'description' => 'Website care plan', 'quantity_milli' => 1000, 'unit_price_minor' => 35000]);
    app(RecalculateInvoice::class)($draft);
    $issued = Invoice::factory()->for($client)->create();
    $issued->items()->create(['position' => 0, 'description' => 'Plugin work', 'quantity_milli' => 2500, 'unit_price_minor' => 4500]);
    app(IssueInvoice::class)($issued);
    McpAuditLog::query()->create(['method' => 'tools/call', 'client_id' => 'x', 'client_name' => 'Claude Code', 'tool' => 'save-content', 'target_type' => 'post', 'target_id' => '1', 'target_label' => 'A post', 'changes' => ['action' => 'updated', 'fields' => ['title' => []]], 'result' => 'ok', 'http_status' => 200, 'ip' => '203.0.113.24']);

    $screens = [
        adminPath(), adminPath('/projects'), adminPath('/projects/create'), adminPath("/projects/{$project->id}/edit"),
        adminPath('/posts'), adminPath("/posts/{$post->id}/edit"), adminPath('/services'), adminPath('/experiences'),
        adminPath('/categories'), adminPath('/tags'), adminPath('/redirects'), adminPath('/clients'),
        adminPath('/invoices'), adminPath("/invoices/{$draft->id}/edit"), adminPath("/invoices/{$issued->id}/edit"),
        adminPath('/settings/profile'), adminPath('/settings/seo'), adminPath('/settings/invoicing'), adminPath('/settings/payment'),
        adminPath('/settings/mcp'), adminPath('/mcp/apps'), adminPath('/mcp/audit'), adminPath('/profile'),
    ];

    // Admin screens fade in for 160 ms; check once the fade is done.
    foreach ($screens as $url) {
        visit($url)->wait(0.4)->assertNoAccessibilityIssues(3);
    }
});

it('has no accessibility issues on the admin login', function (): void {
    visit(adminPath('/login'))->assertNoAccessibilityIssues(3);
});

it('starts the tab order with a visible skip link that reaches the content', function (string $url): void {
    $page = visit($url)->keys('[lang="en"]', 'Tab');

    expect($page->script("(() => { const a = document.activeElement; const r = a.getBoundingClientRect(); return a.classList.contains('skip-link') && r.top >= 0 && r.height > 0; })()"))->toBeTrue()
        ->and($page->script("document.getElementById(document.activeElement.getAttribute('href').slice(1)) !== null"))->toBeTrue();
})->with(['/', '/projects', '/projects/case-one', '/blog', '/blog/a-post']);

it('shows a visible focus ring on every focusable element', function (string $url): void {
    // One real Tab puts the browser in keyboard mode, so focus() below matches :focus-visible like tabbing does.
    $page = visit($url)->keys('[lang="en"]', 'Tab');

    $missing = $page->script(<<<'JS'
        (() => {
            const missing = [];
            const els = [...document.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"])')]
                .filter((el) => !el.disabled && el.getClientRects().length > 0);
            for (const el of els) {
                el.focus();
                const s = getComputedStyle(el);
                const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
                if (!visible) missing.push(el.outerHTML.slice(0, 120));
            }
            return [...new Set(missing)];
        })()
        JS);

    expect($missing)->toBe([]);
})->with(['/', '/projects/case-one', '/blog/a-post']);
