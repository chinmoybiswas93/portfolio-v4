<?php

declare(strict_types=1);

use App\Enums\PublishStatus;
use App\Filament\Pages\ManagePayment;
use App\Filament\Pages\ManageProfile;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Services\Pages\CreateService;
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

it('rejects duplicate slugs and unsafe slug characters', function (): void {
    Project::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'X', 'slug' => 'taken', 'status' => 'draft'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'X', 'slug' => '../etc', 'status' => 'draft'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
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
