<?php

declare(strict_types=1);

use App\Enums\TagType;
use App\Models\Experience;
use App\Models\Project;
use App\Settings\ProfileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->fixture = base_path('tests/Fixtures/legacy-export.json');
    $this->assets = sys_get_temp_dir().'/legacy-assets-'.uniqid();
    mkdir($this->assets.'/projects', 0777, true);
    $image = imagecreatetruecolor(40, 25);
    imagepng($image, $this->assets.'/projects/sample-shop.png');
    imagepng($image, $this->assets.'/projects/Sample-–-Plugin.png');
    file_put_contents($this->assets.'/resume.pdf', '%PDF-1.4 test');
});

afterEach(function (): void {
    @unlink($this->assets.'/projects/sample-shop.png');
    @unlink($this->assets.'/projects/Sample-–-Plugin.png');
    @unlink($this->assets.'/resume.pdf');
    @rmdir($this->assets.'/projects');
    @rmdir($this->assets);
});

function runImport(array $options = []): void
{
    test()->artisan('import:legacy-portfolio', ['path' => test()->fixture] + $options)->assertSuccessful();
}

it('imports experience with parsed dates, current role and normalised tags', function (): void {
    runImport();

    $current = Experience::query()->where('company', 'Acme Plugins')->firstOrFail();
    $past = Experience::query()->where('company', 'Beta Agency')->firstOrFail();

    expect($current->started_on->toDateString())->toBe('2024-05-01')
        ->and($current->isCurrent())->toBeTrue()
        ->and($past->ended_on?->toDateString())->toBe('2024-04-01')
        ->and($current->tags()->pluck('name')->all())->toContain('WordPress Plugin Dev')
        ->and($past->tags()->pluck('name')->sort()->values()->all())->toBe(['JavaScript', 'PHP', 'WooCommerce', 'WordPress'])
        ->and($past->tags()->first()?->type)->toBe(TagType::Tech);
});

it('imports projects as published, without case studies', function (): void {
    runImport();

    $shop = Project::query()->where('slug', 'sample-shop')->firstOrFail();
    $plugin = Project::query()->where('slug', 'sample-plugin')->firstOrFail();

    expect($shop->is_featured)->toBeTrue()
        ->and($shop->has_case_study)->toBeFalse()
        ->and($shop->year)->toBe(2022)
        ->and($shop->repo_url)->toBeNull()
        ->and($plugin->repo_url)->toBe('https://github.com/example/plugin')
        ->and($plugin->tags()->pluck('name')->sort()->values()->all())->toBe(['PHP', 'Vue.js']);
});

it('maps the profile and never imports email, phone, address or unsafe links', function (): void {
    runImport();
    $profile = app(ProfileSettings::class);

    expect($profile->name)->toBe('Test Person')
        ->and($profile->about_md)->toContain('**bold**')
        ->and($profile->about_html)->not->toContain('<script>')
        ->and($profile->location_public)->toBeNull()
        ->and($profile->email)->toBeNull()
        ->and(collect($profile->socials)->pluck('network')->all())->toBe(['github', 'linkedin'])
        ->and(json_encode(app(ProfileSettings::class)->toArray()))->not->toContain('0000 000000')->not->toContain('Example Street');
});

it('attaches cover images and the cv from the assets folder', function (): void {
    runImport(['--assets' => $this->assets]);

    $shop = Project::query()->where('slug', 'sample-shop')->firstOrFail();
    expect($shop->getFirstMedia('cover')?->getCustomProperty('alt'))->toBe('Sample Shop website')
        ->and(Project::query()->where('slug', 'sample-plugin')->firstOrFail()->getFirstMedia('cover'))->not->toBeNull()
        ->and(app(ProfileSettings::class)->cv_path)->toBe('cv/resume.pdf');
    Storage::disk('local')->assertExists('cv/resume.pdf');
});

it('is idempotent', function (): void {
    runImport(['--assets' => $this->assets]);
    runImport(['--assets' => $this->assets]);

    expect(Experience::query()->count())->toBe(2)
        ->and(Project::query()->count())->toBe(2)
        ->and(Project::query()->where('slug', 'sample-shop')->firstOrFail()->getMedia('cover'))->toHaveCount(1);
});

it('saves nothing on a dry run', function (): void {
    runImport(['--dry-run' => true]);

    expect(Experience::query()->count())->toBe(0)->and(Project::query()->count())->toBe(0);
});

it('fails cleanly on a missing or invalid file', function (): void {
    $this->artisan('import:legacy-portfolio', ['path' => '/nope.json'])->assertFailed();
});
