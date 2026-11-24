<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Markdown\TableOfContents;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PostController
{
    public const PER_PAGE = 10;

    /** Tag archives with fewer published posts are thin: noindex, follow, and left out of the sitemap (ADR-0010). */
    public const MIN_INDEXED_TAG_POSTS = 3;

    public function index(): View
    {
        return $this->list(Post::query());
    }

    public function category(Category $category): View
    {
        $count = $category->posts()->published()->count();

        return $this->list(
            Post::query()->whereBelongsTo($category), $category->name, $category->slug,
            description: $category->description ?: self::archiveDescription($count, 'about '.$category->name),
            intro: $category->description,
        );
    }

    public function tag(Tag $tag): View
    {
        $count = $tag->posts()->published()->count();
        abort_if($count === 0, 404);

        return $this->list(
            Post::query()->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->getKey())), '#'.$tag->name,
            description: $tag->description ?: self::archiveDescription($count, 'tagged '.$tag->name),
            noindex: ! self::tagIsIndexable($tag, $count),
        );
    }

    /**
     * A tag archive is indexed when it has enough posts and does not repeat a category with the same slug (the
     * category is the hub for that topic).
     */
    public static function tagIsIndexable(Tag $tag, int $publishedPosts): bool
    {
        return $publishedPosts >= self::MIN_INDEXED_TAG_POSTS && ! Category::query()->where('slug', $tag->slug)->exists();
    }

    /** A unique description for an archive page, e.g. "4 posts about WordPress: fixes, case notes and how-tos …". */
    public static function archiveDescription(int $count, string $topic): string
    {
        return $count.' '.str('post')->plural($count).' '.$topic.': real client problems, how I fixed them, and what to check on your own site.';
    }

    public function show(Post $post): View
    {
        $isLive = in_array($post->status, [PostStatus::Published, PostStatus::Scheduled], true)
            && $post->published_at !== null && $post->published_at->isPast();
        abort_unless($isLive, 404);
        $post->load(['category', 'tags', 'media']);

        $toc = TableOfContents::build((string) $post->body_html);

        return view('site.posts.show', [
            'post' => $post,
            'body' => $toc['html'],
            'contents' => count($toc['items']) >= 2 ? $toc['items'] : [],
            'related' => $this->related($post),
            'previous' => Post::query()->published()->where('published_at', '<', $post->published_at)->first(['title', 'slug']),
            'next' => Post::query()->published()->reorder()->where('published_at', '>', $post->published_at)->orderBy('published_at')->first(['title', 'slug']),
        ]);
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function list(Builder $query, ?string $heading = null, ?string $activeCategory = null, ?string $description = null, ?string $intro = null, bool $noindex = false): View
    {
        $posts = $query->published()->with(['category', 'media'])->paginate(self::PER_PAGE);
        abort_if($posts->currentPage() > max(1, $posts->lastPage()), 404); // no endless empty pages

        return view('site.posts.index', [
            'posts' => $posts,
            'categories' => Category::query()->whereHas('posts', fn (Builder $q) => $q->published())->orderBy('name')->get(),
            'heading' => $heading,
            'activeCategory' => $activeCategory,
            'description' => $description,
            'intro' => $intro,
            'noindex' => $noindex,
        ]);
    }

    /**
     * Up to three published posts sharing the most tags, then the same category.
     *
     * @return Collection<int, Post>
     */
    private function related(Post $post): Collection
    {
        $tagIds = $post->tags->modelKeys();
        $query = Post::query()->published()->whereKeyNot($post->getKey())->reorder();

        $byTags = $tagIds === [] ? new \Illuminate\Database\Eloquent\Collection : (clone $query)
            ->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds))
            ->withCount(['tags as shared_tags' => fn (Builder $q) => $q->whereIn('tags.id', $tagIds)])
            ->orderByDesc('shared_tags')->orderByDesc('published_at')
            ->limit(3)->get(['id', 'title', 'slug', 'published_at']);

        if ($byTags->count() < 3 && $post->category_id !== null) {
            $byTags = $byTags->concat((clone $query)
                ->where('category_id', $post->category_id)
                ->whereKeyNot($byTags->modelKeys())
                ->orderByDesc('published_at')
                ->limit(3 - $byTags->count())->get(['id', 'title', 'slug', 'published_at']));
        }

        return $byTags->values();
    }
}
