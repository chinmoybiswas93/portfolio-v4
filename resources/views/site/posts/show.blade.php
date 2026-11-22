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
        <div @class(['post-layout', 'has-contents' => $contents !== []])>
        <main id="content" class="min-w-0 pb-24 pt-8 md:pt-12">
            <article class="flex flex-col gap-[22px]">
                <nav class="crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('posts.index') }}">Writing</a>
                    @if ($post->category)
                        <span aria-hidden="true">/</span> <a href="{{ route('posts.category', $post->category) }}">{{ $post->category->name }}</a>
                    @endif
                    <span aria-hidden="true">/</span> <span aria-current="page">{{ $post->title }}</span>
                </nav>
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

                @if ($contents !== [])
                    <details class="toc-mobile" open>
                        <summary>On this page</summary>
                        <ol>
                            @foreach ($contents as $item)
                                <li @class(['toc-sub' => $item['level'] === 3])><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                @endif

                @if ($cover = $post->getFirstMedia('cover'))
                    <figure class="m-0 my-2">
                        <x-site.media-image :media="$cover" conversion="hero" sizes="(min-width: 728px) 680px, 100vw" :width="680" :height="425" loading="eager" fetchpriority="high" class="cs-image" />
                    </figure>
                @endif

                <div class="prose">{!! $body !!}</div>

                @php($topics = $post->tags)
                @if ($topics->isNotEmpty())
                    <ul class="chips mt-3" aria-label="Tags">
                        @foreach ($topics as $tag)
                            <li><a class="chip inline-block" href="{{ route('posts.tag', $tag) }}">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                @endif

                @php($shareUrl = \App\Support\Seo\Seo::absolute(route('posts.show', $post, false)))
                <div class="share" aria-label="Share this post">
                    <span class="share-label">Share</span>
                    <a class="share-btn" href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer">LinkedIn<span class="sr-only"> (opens in a new tab)</span></a>
                    <a class="share-btn" href="https://x.com/intent/post?url={{ rawurlencode($shareUrl) }}&amp;text={{ rawurlencode($post->title) }}" target="_blank" rel="noopener noreferrer">X<span class="sr-only"> (opens in a new tab)</span></a>
                    <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer">Facebook<span class="sr-only"> (opens in a new tab)</span></a>
                    <button type="button" class="share-btn" data-copy-link="{{ $shareUrl }}">Copy link</button>
                    <span class="sr-only" role="status" data-copy-status></span>
                </div>

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

                @if ($related->isNotEmpty())
                    <section class="related" aria-labelledby="related-h">
                        <h2 id="related-h" class="related-title">Related posts</h2>
                        <ul>
                            @foreach ($related as $item)
                                <li><a class="row-link" href="{{ route('posts.show', $item) }}"><span class="row-title">{{ $item->title }}</span><time datetime="{{ $item->published_at?->toDateString() }}">{{ $item->published_at?->format('j M Y') }}</time></a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <a class="self-start text-sm text-[var(--muted)]" href="{{ route('posts.index') }}">← Back to writing</a>
            </article>
        </main>
        @if ($contents !== [])
            <aside class="toc" aria-label="On this page">
                <p class="toc-title">On this page</p>
                <ol>
                    @foreach ($contents as $item)
                        <li @class(['toc-sub' => $item['level'] === 3])><a href="#{{ $item['id'] }}" data-toc-link="{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ol>
                <p class="toc-top"><a href="#content">↑ Back to top</a></p>
            </aside>
        @endif
        </div>
        <footer class="page-footer post-footer"><x-site.footer /></footer>
    </div>
</x-layouts.site>
