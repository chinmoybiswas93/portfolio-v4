<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

final class PostController
{
    public const PER_PAGE = 10;

    public function index(): View
    {
        return $this->list(Post::query());
    }

    public function category(Category $category): View
    {
        return $this->list(Post::query()->whereBelongsTo($category), $category->name, $category->slug);
    }

    public function tag(Tag $tag): View
    {
        abort_unless($tag->posts()->published()->exists(), 404);

        return $this->list(Post::query()->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->getKey())), '#'.$tag->name);
    }

    public function show(Post $post): View
    {
        $isLive = in_array($post->status, [PostStatus::Published, PostStatus::Scheduled], true)
            && $post->published_at !== null && $post->published_at->isPast();
        abort_unless($isLive, 404);
        $post->load(['category', 'tags', 'media']);

        return view('site.posts.show', [
            'post' => $post,
            'previous' => Post::query()->published()->where('published_at', '<', $post->published_at)->first(['title', 'slug']),
            'next' => Post::query()->published()->reorder()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first(['title', 'slug']),
        ]);
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function list(Builder $query, ?string $heading = null, ?string $activeCategory = null): View
    {
        return view('site.posts.index', [
            'posts' => $query->published()->with(['category', 'media'])->paginate(self::PER_PAGE),
            'categories' => Category::query()->whereHas('posts', fn (Builder $q) => $q->published())->orderBy('name')->get(),
            'heading' => $heading,
            'activeCategory' => $activeCategory,
        ]);
    }
}
