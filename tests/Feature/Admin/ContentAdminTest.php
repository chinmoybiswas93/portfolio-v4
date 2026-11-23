<?php

declare(strict_types=1);

use App\Enums\PublishStatus;
use App\Filament\Pages\ManagePayment;
use App\Filament\Pages\ManageProfile;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Widgets\RecentPosts;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Settings\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(adminWithMfa()));

it('creates a service and renders its markdown', function (): void {
    Livewire::test(CreateService::class)
        ->fillForm(['title' => 'Speed audits', 'slug' => 'speed-audits', 'summary' => 'Faster sites.', 'body_md' => '**Fast**'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Service::query()->where('slug', 'speed-audits')->firstOrFail()->body_html)->toContain('<strong>Fast</strong>');
});

it('creates a project and sets the publish date when published', function (): void {
    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'New Site', 'slug' => 'new-site', 'status' => PublishStatus::Published->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->where('slug', 'new-site')->firstOrFail()->published_at)->not->toBeNull();
});

it('rejects duplicate slugs and cleans unsafe slug characters', function (): void {
    Project::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'X', 'slug' => 'taken', 'status' => 'draft'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'X', 'slug' => '../etc', 'status' => 'draft'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->where('slug', 'etc')->exists())->toBeTrue();
});

it('stores payment details encrypted from the settings page', function (): void {
    Livewire::test(ManagePayment::class)
        ->fillForm(['bank_details' => 'Sort code 11-22-33', 'paypal_email' => 'pay@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(PaymentSettings::class)->bank_details)->toBe('Sort code 11-22-33')
        ->and((string) DB::table('settings')->where('name', 'bank_details')->value('payload'))->not->toContain('11-22-33');
});

it('rejects SVG uploads for public images', function (): void {
    Storage::fake('public');
    $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    Livewire::test(ManageProfile::class)
        ->fillForm(['avatar_path' => $svg])
        ->call('save')
        ->assertHasFormErrors(['avatar_path']);
});

it('stores project images uploaded in admin on the public disk', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    Livewire::test(CreateProject::class)
        ->fillForm([
            'title' => 'With images', 'slug' => 'with-images', 'status' => 'draft',
            'cover' => [UploadedFile::fake()->image('cover.jpg', 800, 500)],
            'cover_alt' => 'Home page',
            'gallery' => [UploadedFile::fake()->image('shot.jpg', 800, 500)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::query()->where('slug', 'with-images')->firstOrFail();
    expect($project->getFirstMedia('cover')?->disk)->toBe('public')
        ->and($project->getFirstMedia('cover')?->getCustomProperty('alt'))->toBe('Home page')
        ->and($project->getFirstMedia('gallery')?->disk)->toBe('public');
});

it('publishes a project by setting the status and saving', function (): void {
    $project = Project::factory()->draft()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->fillForm(['status' => PublishStatus::Published->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->refresh()->status)->toBe(PublishStatus::Published)
        ->and($project->published_at)->not->toBeNull();
});

it('fills the slug from the title when creating', function (): void {
    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'My New Site'])
        ->assertSchemaStateSet(['slug' => 'my-new-site']);
});

it('locks the slug after saving until Edit is used', function (): void {
    $project = Project::factory()->create(['title' => 'Locked', 'slug' => 'locked']);

    $page = Livewire::test(EditProject::class, ['record' => $project->getRouteKey()]);
    $page->assertFormFieldIsReadOnly('slug');

    $page->set('data.slug_unlocked', true)
        ->fillForm(['slug' => 'unlocked'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->refresh()->slug)->toBe('unlocked');
});

it('shows the five most recently edited posts on the dashboard', function (): void {
    Post::factory()->create(['title' => 'Oldest post']);
    $this->travel(1)->minute();
    Post::factory()->count(4)->create();
    $this->travel(1)->minute();
    Post::factory()->draft()->create(['title' => 'Fresh draft']);

    Livewire::test(RecentPosts::class)
        ->assertSee('Fresh draft')->assertSee('Draft · edited today')->assertDontSee('Oldest post');
});

it('edits small resources in a modal and keeps their slug rules', function (): void {
    $category = Category::factory()->create(['name' => 'WordPress', 'slug' => 'wordpress']);

    Livewire::test(ManageCategories::class)
        ->callTableAction('edit', $category, data: ['name' => 'WordPress tips'])
        ->assertHasNoTableActionErrors();

    expect($category->refresh()->name)->toBe('WordPress tips');
});

it('puts Cancel, Preview, Delete and Save in the edit page header', function (): void {
    $project = Project::factory()->caseStudy()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertActionVisible('cancel')->assertActionVisible('preview')->assertActionVisible('delete')->assertActionVisible('save')
        ->assertActionHasUrl('cancel', ProjectResource::getUrl('index'));
});

it('shows the settings groups as tabs of one Settings page', function (): void {
    $this->get(adminPath('/settings'))->assertRedirect(adminPath('/settings/profile'));
    $this->get(adminPath('/settings/profile'))->assertOk()->assertSee('Profile')->assertSee('SEO')->assertSee('Invoicing')->assertSee('Payment')->assertSee('Save changes');
});
