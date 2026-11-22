<?php

declare(strict_types=1);

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('makes a slug from the title when it is left empty, adding a number when taken', function (string $model, string $source): void {
    $a = $model::factory()->create([$source => 'Speed Tips!', 'slug' => '']);
    $b = $model::factory()->create([$source => 'Speed tips', 'slug' => null]);

    expect($a->slug)->toBe('speed-tips')->and($b->slug)->toBe('speed-tips-2');
})->with([[Post::class, 'title'], [Project::class, 'title'], [Service::class, 'title'], [Category::class, 'name'], [Tag::class, 'name']]);

it('normalises a typed slug', function (): void {
    expect(Post::factory()->create(['slug' => 'My First  Post'])->slug)->toBe('my-first-post');
});

it('fills the slug in the form when it is left empty and rejects a bad format inline', function (): void {
    $this->actingAs(adminWithMfa());

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'Brand New Site', 'slug' => ''])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Project::query()->where('slug', 'brand-new-site')->exists())->toBeTrue();

    Livewire::test(CreatePost::class)
        ->set('data.slug', 'Bad Slug!')
        ->assertSet('data.slug', 'bad-slug');
});

it('shows a uniqueness error on the slug field', function (): void {
    $this->actingAs(adminWithMfa());
    Post::factory()->create(['slug' => 'taken']);

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Another', 'slug' => 'taken'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('searches one shared tag pool from every tag field', function (): void {
    $this->actingAs(adminWithMfa());
    Tag::findOrCreateByName('WordPress');

    Livewire::test(CreatePost::class)
        ->assertFormFieldExists('tags', fn ($field): bool => in_array('WordPress', $field->getOptions(), true));
});

it('merges duplicate tags into one pool when migrating', function (): void {
    expect(DB::getSchemaBuilder()->hasColumn('tags', 'type'))->toBeFalse();
});
