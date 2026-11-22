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
                    <a class="share-btn" href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0A66C2" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 1 1 0-4.125 2.062 2.062 0 0 1 0 4.125zM7.119 20.452H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>LinkedIn<span class="sr-only"> (opens in a new tab)</span></a>
                    <a class="share-btn" href="https://x.com/intent/post?url={{ rawurlencode($shareUrl) }}&amp;text={{ rawurlencode($post->title) }}" target="_blank" rel="noopener noreferrer"><svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg>X<span class="sr-only"> (opens in a new tab)</span></a>
                    <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0866FF" d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z"/></svg>Facebook<span class="sr-only"> (opens in a new tab)</span></a>
                    <button type="button" class="share-btn" data-copy-link="{{ $shareUrl }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg><span data-copy-label>Copy link</span></button>
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
