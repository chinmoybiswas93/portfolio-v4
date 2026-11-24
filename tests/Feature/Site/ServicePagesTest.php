<?php

declare(strict_types=1);

use App\Filament\Resources\Services\Pages\EditService;
use App\Jobs\SubmitToIndexNow;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use App\Settings\ProfileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010 slice 8d: service landing pages with related work, FAQ, a contact box and Service data.

beforeEach(function (): void {
    config(['app.url' => 'https://example.com', 'cache.pages_enabled' => false, 'brand.area_served' => ['GB', 'EU'], 'brand.contact_email' => 'hello@example.com']);
    $profile = app(ProfileSettings::class);
    $profile->name = 'Sam Example';
    $profile->email = null;
    $profile->socials = [['network' => 'linkedin', 'url' => 'https://www.linkedin.com/in/example']];
    $profile->save();
});

/** @return array<string, array<string, mixed>> */
function serviceNodes(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return collect(json_decode($m[1] ?? '{}', true)['@graph'] ?? [])->keyBy('@type')->all();
}

function wooService(array $attributes = []): Service
{
    return Service::factory()->create(array_merge([
        'title' => 'WooCommerce development', 'slug' => 'woocommerce', 'summary' => 'Stores that sell.',
        'body_md' => "## Who it is for\n\nShops that need speed.", 'is_published' => true,
        'faq' => [['question' => 'Can you work on an existing store?', 'answer' => 'Yes. Most projects start with an audit.']],
        'cta_line' => 'Have a WooCommerce project in mind?',
    ], $attributes));
}

it('shows a service page with details, related work, FAQ, contact box and Service data', function (): void {
    $service = wooService();
    $woo = Tag::factory()->create(['name' => 'WooCommerce']);
    $other = Tag::factory()->create(['name' => 'Laravel']);
    $service->tags()->attach($woo);
    Project::factory()->caseStudy()->create(['title' => 'Flamenco bookings', 'slug' => 'flamenco'])->tags()->attach($woo);
    Project::factory()->caseStudy()->draft()->create(['title' => 'Draft case'])->tags()->attach($woo);
    Project::factory()->caseStudy()->create(['title' => 'Laravel app'])->tags()->attach($other);
    Post::factory()->for(Category::factory())->create(['title' => 'Checkout fix', 'slug' => 'checkout'])->tags()->attach($woo);

    $html = (string) $this->get('/services/woocommerce')->assertOk()
        ->assertSee('<h1 class="cs-title">WooCommerce development</h1>', false)
        ->assertSee('<h2>Who it is for</h2>', false)
        ->assertSeeInOrder(['Related work', 'Flamenco bookings', 'Case study', 'Checkout fix', 'Article'])
        ->assertDontSee('Draft case')->assertDontSee('Laravel app')
        ->assertSee('Can you work on an existing store?')
        ->assertSee('Have a WooCommerce project in mind?')
        ->assertSee('href="mailto:hello@example.com?subject=WooCommerce%20development"', false)
        ->assertSee('href="https://www.linkedin.com/in/example"', false)
        ->assertSee('aria-current="page" >Services</a>', false)
        ->getContent();

    $nodes = serviceNodes($html);
    expect($nodes['Service'])->toMatchArray(['name' => 'WooCommerce development', 'url' => 'https://example.com/services/woocommerce', 'areaServed' => ['GB', 'EU']])
        ->and($nodes['Service']['provider']['name'])->toBe('Sam Example')
        ->and($nodes['FAQPage']['mainEntity'][0]['name'])->toBe('Can you work on an existing store?')
        ->and($nodes['BreadcrumbList']['itemListElement'][1]['name'])->toBe('Services');
});

it('gives no page to a service without details or one that is unpublished', function (array $attributes): void {
    wooService($attributes);

    $this->get('/services/woocommerce')->assertNotFound();
})->with([
    'no details' => [['body_md' => '']],
    'unpublished' => [['is_published' => false]],
]);

it('lists services and links only those with a page, from the index and the home page', function (): void {
    wooService();
    Service::factory()->create(['title' => 'Care plans', 'slug' => 'care', 'body_md' => '', 'is_published' => true]);

    $this->get('/services')->assertOk()
        ->assertSee('/services/woocommerce">WooCommerce development', false)
        ->assertSee('Care plans')->assertDontSee('/services/care"', false);
    $this->get('/')->assertSee('/services/woocommerce">WooCommerce development', false);
});

it('adds service pages to the sitemap and pings IndexNow when one is saved', function (): void {
    wooService();
    Service::factory()->create(['slug' => 'care', 'body_md' => '', 'is_published' => true]);

    $this->get('/sitemap.xml')->assertSee('https://example.com/services/woocommerce')->assertSee('<loc>https://example.com/services</loc>', false)
        ->assertDontSee('https://example.com/services/care');

    app()->detectEnvironment(fn (): string => 'production');
    config(['seo.indexnow_key' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90']);
    Queue::fake();
    Service::query()->where('slug', 'woocommerce')->firstOrFail()->update(['summary' => 'Faster stores.']);
    Queue::assertPushed(SubmitToIndexNow::class, fn (SubmitToIndexNow $job): bool => $job->urls === ['https://example.com/services/woocommerce', 'https://example.com/services']);
});

it('edits topics, FAQ, contact heading and SEO in the service editor, with a preview link', function (): void {
    $this->actingAs(adminWithMfa());
    $service = wooService(['faq' => null, 'cta_line' => null]);
    $tag = Tag::factory()->create(['name' => 'WooCommerce']);

    Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
        ->assertActionVisible('preview')
        ->fillForm([
            'tags' => [$tag->id],
            'faq' => [['question' => 'How long does it take?', 'answer' => 'Two to six weeks.']],
            'cta_line' => 'Ready to grow your store?',
            'seo_title' => 'WooCommerce developer for UK stores',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $service->refresh();
    expect($service->tags->pluck('name')->all())->toBe(['WooCommerce'])
        ->and($service->faqItems())->toBe([['question' => 'How long does it take?', 'answer' => 'Two to six weeks.']])
        ->and($service->cta_line)->toBe('Ready to grow your store?')
        ->and($service->seo_title)->toBe('WooCommerce developer for UK stores');
});
