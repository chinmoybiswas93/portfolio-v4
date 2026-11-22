@php
    $profile = app(\App\Settings\ProfileSettings::class);
    $shareImage = \App\Support\Seo\Seo::image($post, 'Writing', $post->title);
    $jsonLd = [
        \App\Support\Seo\JsonLd::blogPosting($post, $shareImage),
        \App\Support\Seo\JsonLd::breadcrumbs([['Home', '/'], ['Writing', '/blog'], [$post->title, route('posts.show', $post, false)]]),
    ];
@endphp
<x-layouts.site :title="$post->seo_title ?: $post->title" :description="$post->seo_description ?: $post->excerpt" type="article" :image="$shareImage"
    :published="$post->published_at?->toAtomString()" :modified="$post->updated_at?->toAtomString()" :json-ld="$jsonLd">
    <div class="page-wrap">
        <x-site.topbar current="writing" />
        <main id="content" class="mx-auto max-w-[680px] pb-24 pt-8 md:pt-12">
            <article class="flex flex-col gap-[22px]">
                <a class="self-start text-sm text-[var(--dim)]" href="{{ route('posts.index') }}">← All writing</a>
                <p class="post-meta">
                    @if ($post->category)
                        <a class="cat" href="{{ route('posts.category', $post->category) }}">{{ $post->category->name }}</a> ·
                    @endif
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time> · {{ $post->reading_minutes }} min read
                </p>
                <h1 class="post-title">{{ $post->title }}</h1>
                @if ($post->excerpt)
                    <p class="post-lead">{{ $post->excerpt }}</p>
                @endif

                @if ($cover = $post->getFirstMedia('cover'))
                    <figure class="m-0 my-2">
                        <x-site.media-image :media="$cover" conversion="hero" sizes="(min-width: 728px) 680px, 100vw" :width="680" :height="425" loading="eager" fetchpriority="high" class="cs-image" />
                    </figure>
                @endif

                <div class="prose">{!! $post->body_html !!}</div>

                @php($topics = $post->tags)
                @if ($topics->isNotEmpty())
                    <ul class="chips mt-3" aria-label="Tags">
                        @foreach ($topics as $tag)
                            <li><a class="chip inline-block" href="{{ route('posts.tag', $tag) }}">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                @endif

                <aside class="author-box" aria-label="About the author">
                    <p class="font-semibold text-[var(--text)]">{{ $profile->name }}</p>
                    <p class="text-[15.5px]">{{ $profile->tagline }} <a class="text-[var(--text)] underline decoration-[var(--dim)] underline-offset-4" href="{{ route('home') }}">More about me</a></p>
                </aside>

                @if ($previous || $next)
                    <nav class="pager border-t-0 pt-0" aria-label="More posts">
                        @if ($previous)
                            <a href="{{ route('posts.show', $previous) }}"><span class="pager-label">← Previous</span><span class="pager-title">{{ $previous->title }}</span></a>
                        @endif
                        @if ($next)
                            <a class="next" href="{{ route('posts.show', $next) }}"><span class="pager-label">Next →</span><span class="pager-title">{{ $next->title }}</span></a>
                        @endif
                    </nav>
                @endif
            </article>
        </main>
        <footer class="page-footer mx-auto max-w-[680px]"><x-site.footer /></footer>
    </div>
</x-layouts.site>
