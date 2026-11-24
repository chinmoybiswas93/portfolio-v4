<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use App\Settings\SeoSettings;
use App\Support\Seo\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * sitemap.xml, feed.xml and robots.txt (docs/ARCHITECTURE.md §6). Served through the public page cache.
 */
final class SeoController
{
    public function sitemap(): Response
    {
        $posts = Post::query()->published()->get(['id', 'slug', 'updated_at']);
        $projects = Project::query()->published()->where('has_case_study', true)->get(['id', 'slug', 'updated_at']);
        $latestPost = $posts->max('updated_at');

        $urls = collect([
            ['/', collect([$latestPost, $projects->max('updated_at')])->filter()->max()],
            ['/projects', Project::query()->published()->max('updated_at')],
            ['/blog', $latestPost],
        ])
            ->concat($projects->map(fn (Project $p): array => [route('projects.show', $p, false), $p->updated_at]))
            ->concat($posts->map(fn (Post $p): array => [route('posts.show', $p, false), $p->updated_at]))
            ->concat(Category::query()->whereHas('posts', fn (Builder $q) => $q->published())->get(['slug'])
                ->map(fn (Category $c): array => [route('posts.category', $c, false), null]))
            // Thin tag archives are noindex, so they stay out of the sitemap (ADR-0010).
            ->concat(Tag::query()->whereHas('posts', fn (Builder $q) => $q->published(), '>=', PostController::MIN_INDEXED_TAG_POSTS)
                ->whereNotIn('slug', Category::query()->select('slug'))->get(['slug'])
                ->map(fn (Tag $t): array => [route('posts.tag', $t, false), null]))
            ->map(fn (array $u): array => ['loc' => Seo::absolute($u[0]), 'lastmod' => $u[1] ? Carbon::parse($u[1])->toAtomString() : null]);

        return response()->view('seo.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function feed(SeoSettings $seo): Response
    {
        $posts = Post::query()->published()->with('category')->limit(20)->get();

        return response()->view('seo.feed', [
            'posts' => $posts,
            'title' => $seo->site_title.' — Writing',
            'description' => $seo->default_description,
        ], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = Seo::indexable()
            // The admin path is never listed here (it is private); admin pages send X-Robots-Tag: noindex instead.
            ? "User-agent: *\nAllow: /\n\nSitemap: ".Seo::absolute('/sitemap.xml')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
