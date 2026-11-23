<?php

declare(strict_types=1);

use App\Models\Post;
use App\Support\Cache\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('only builds cache keys for sane page numbers', function (string $query, bool $cached): void {
    $key = app(PageCache::class)->key(Request::create('/blog'.$query));

    expect($key !== null)->toBe($cached);
})->with([
    'no page' => ['', true],
    'page 2' => ['?page=2', true],
    'last cached page' => ['?page='.PageCache::MAX_PAGE, true],
    'too high' => ['?page='.(PageCache::MAX_PAGE + 1), false],
    'zero' => ['?page=0', false],
    'text' => ['?page=aaa1', false],
    'array' => ['?page[]=1', false],
    'other params are ignored' => ['?utm_source=x', true],
]);

it('answers 404 past the last blog page', function (): void {
    Post::factory()->create();

    $this->get('/blog?page=2')->assertNotFound();
    $this->get('/blog?page=1')->assertOk();
});
