<?php

declare(strict_types=1);

use App\Enums\TagType;
use App\Models\Experience;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use App\Settings\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('renders markdown columns when a model is saved', function (): void {
    $project = Project::factory()->create(['problem_md' => 'The **problem**.']);

    expect($project->problem_html)->toContain('<strong>problem</strong>');

    $project->update(['problem_md' => 'Changed.']);
    expect($project->fresh()->problem_html)->toContain('Changed.');
});

it('computes reading time for posts', function (): void {
    $post = Post::factory()->create(['body_md' => str_repeat('word ', 700)]);

    expect($post->reading_minutes)->toBe(4);
});

it('only lists published projects and posts', function (): void {
    Project::factory()->create(['title' => 'Live one', 'slug' => 'live-one']);
    Project::factory()->draft()->create(['title' => 'Hidden one', 'slug' => 'hidden-one']);
    Post::factory()->create();
    Post::factory()->draft()->create();
    Post::factory()->create(['published_at' => now()->addDay()]);

    expect(Project::query()->published()->pluck('slug')->all())->toBe(['live-one'])
        ->and(Post::query()->published()->count())->toBe(1);
});

it('treats an experience without an end date as current', function (): void {
    expect(Experience::factory()->current()->create()->isCurrent())->toBeTrue()
        ->and(Experience::factory()->create()->isCurrent())->toBeFalse();
});

it('syncs tags by name without duplicates and keeps tag types apart', function (): void {
    $project = Project::factory()->create();
    $project->syncTagNames(['PHP', 'PHP ', 'WordPress'], TagType::Tech);
    Post::factory()->create()->syncTagNames(['PHP'], TagType::Topic);

    expect($project->tags()->pluck('name')->sort()->values()->all())->toBe(['PHP', 'WordPress'])
        ->and(Tag::query()->where('slug', 'php')->count())->toBe(2);
});

it('stores the morph alias instead of the class name', function (): void {
    Project::factory()->create()->syncTagNames(['Laravel'], TagType::Tech);

    expect(DB::table('taggables')->value('taggable_type'))->toBe('project');
});

it('encrypts payment details at rest', function (): void {
    $settings = app(PaymentSettings::class);
    $settings->bank_details = 'Sort code 00-00-00';
    $settings->save();

    $raw = (string) DB::table('settings')->where('group', 'payment')->where('name', 'bank_details')->value('payload');
    expect($raw)->not->toContain('Sort code')
        ->and(app(PaymentSettings::class)->bank_details)->toBe('Sort code 00-00-00');
});
