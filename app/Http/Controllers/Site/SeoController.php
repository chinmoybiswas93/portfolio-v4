<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tag;
use App\Settings\ProfileSettings;
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
        $services = Service::query()->withPage()->get(['id', 'slug', 'updated_at']);
        $latestPost = $posts->max('updated_at');

        $urls = collect([
            ['/', collect([$latestPost, $projects->max('updated_at')])->filter()->max()],
            ['/projects', Project::query()->published()->reorder()->max('updated_at')],
            ['/blog', $latestPost],
            ['/services', Service::query()->published()->reorder()->max('updated_at')],
        ])
            ->concat($services->map(fn (Service $s): array => [route('services.show', $s, false), $s->updated_at]))
            ->concat($projects->map(fn (Project $p): array => [route('projects.show', $p, false), $p->updated_at]))
            ->concat($posts->map(fn (Post $p): array => [route('posts.show', $p, false), $p->updated_at]))
            ->concat(Category::query()->whereHas('posts', fn (Builder $q) => $q->published())->get(['slug'])
                ->map(fn (Category $c): array => [route('posts.category', $c, false), null]))
            // Thin tag archives are noindex, so they stay out of the sitemap (ADR-0010). Aggregates drop the
            // scopes' ORDER BY: MariaDB rejects ordering inside COUNT/MAX queries.
            ->concat(Tag::query()->whereHas('posts', fn (Builder $q) => $q->published()->reorder(), '>=', PostController::MIN_INDEXED_TAG_POSTS)
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
        if (! Seo::indexable()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        // The admin path is never listed here (it is private); admin pages send X-Robots-Tag: noindex instead.
        $lines = ['User-agent: *', 'Allow: /', ''];
        $groups = ['Allow: /' => (array) config('seo.allowed_crawlers'), 'Disallow: /' => (array) config('seo.disallowed_crawlers')];
        foreach ($groups as $rule => $agents) {
            if ($agents === []) {
                continue;
            }
            $lines[] = $rule === 'Allow: /' ? '# Search, answer and AI crawlers: welcome (ADR-0010).' : '# Opted out (ADR-0010).';
            foreach ($agents as $agent) {
                $lines[] = 'User-agent: '.$agent;
            }
            $lines[] = $rule;
            $lines[] = '';
        }
        $lines[] = 'Sitemap: '.Seo::absolute('/sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * /llms.txt (llmstxt.org): a Markdown map of the site for AI assistants, built from the database (ADR-0010).
     */
    public function llms(ProfileSettings $profile, SeoSettings $seo): Response
    {
        $section = function (string $heading, iterable $items): string {
            $lines = [];
            foreach ($items as [$title, $url, $summary]) {
                $lines[] = '- ['.self::md($title).']('.$url.')'.(filled($summary) ? ': '.self::md((string) $summary) : '');
            }

            return $lines === [] ? '' : "## {$heading}\n\n".implode("\n", $lines)."\n\n";
        };

        $services = Service::query()->published()->get()
            ->map(fn (Service $s): array => [$s->title, Seo::absolute($s->hasPage() ? route('services.show', $s, false) : '/#services'), $s->summary]);
        $projects = Project::query()->published()->where('has_case_study', true)->get()
            ->map(fn (Project $p): array => [$p->title, Seo::absolute(route('projects.show', $p, false)), $p->seo_description ?: $p->summary]);
        $posts = Post::query()->published()->get()
            ->map(fn (Post $p): array => [$p->title, Seo::absolute(route('posts.show', $p, false)), $p->seo_description ?: $p->excerpt]);

        $area = implode(', ', (array) config('brand.area_served'));
        $intro = trim(implode(' ', array_filter([
            $profile->title ? self::md($profile->name.' — '.$profile->title).'.' : null,
            $profile->location_public ? 'Based in '.self::md($profile->location_public).($area !== '' ? ', working with clients in '.$area : '').'.' : null,
            'Contact: '.($profile->email ?: config('brand.contact_email')).'.',
        ])));

        $body = '# '.self::md($profile->name)."\n\n"
            .'> '.self::md($seo->default_description)."\n\n"
            .($intro !== '' ? $intro."\n\n" : '')
            .$section('Services', $services)
            .$section('Case studies', $projects)
            .$section('Writing', $posts)
            .$section('Optional', [
                ['All projects', Seo::absolute('/projects'), null],
                ['All writing', Seo::absolute('/blog'), null],
                ['RSS feed', Seo::absolute('/feed.xml'), null],
                ['Sitemap', Seo::absolute('/sitemap.xml'), null],
            ]);

        return response(rtrim($body)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** The IndexNow key file: answers with the key only when one is configured. */
    public function indexNowKey(): Response
    {
        $key = (string) config('seo.indexnow_key');
        abort_if($key === '', 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** One line of Markdown text: no newlines, and link brackets escaped. */
    private static function md(string $text): string
    {
        return str_replace(['[', ']', "\n", "\r"], ['\\[', '\\]', ' ', ''], trim($text));
    }
}
