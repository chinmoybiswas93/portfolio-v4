<x-layouts.site :title="$heading ? 'Writing: '.$heading : 'Writing'" :description="$description ?? 'Notes from building and supporting WordPress and Laravel sites: fixes, checklists and things I learned the hard way.'" :noindex="$noindex ?? false"
    :json-ld="[\App\Support\Seo\JsonLd::collection($heading ? 'Writing: '.$heading : 'Writing', request()->getPathInfo(), $posts->map(fn ($p): array => [$p->title, route('posts.show', $p, false)])->values()->all())]">
    <div class="page-wrap">
        <x-site.topbar current="writing" />
        <main id="content" class="flex flex-col gap-10 pb-24 pt-8 md:pt-12">
            <div class="flex flex-col gap-3">
                <h1 class="page-title">{{ $heading ?? 'Writing' }}</h1>
                <p class="m-0 max-w-[560px]">{{ $intro ?? 'Notes from building and supporting WordPress and Laravel sites: fixes, checklists and things I learned the hard way.' }}</p>
            </div>

            @if ($categories->isNotEmpty())
                <nav class="filters" aria-label="Categories">
                    <a href="{{ route('posts.index') }}" @if ($heading === null) aria-current="page" @endif>All</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('posts.category', $category) }}" @if ($activeCategory === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($posts->isEmpty())
                <p class="m-0">No posts here yet.</p>
            @else
                <ul class="post-list -mt-3 m-0 list-none p-0">
                    @foreach ($posts as $post)
                        <li class="post-row">
                            @php($cover = $post->getFirstMedia('cover'))
                            <a class="row-link post-grid" href="{{ route('posts.show', $post) }}">
                                <p class="date-label m-0"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time></p>
                                <div class="post-thumb">
                                    @if ($cover)
                                        <x-site.media-image :media="$cover" conversion="card" sizes="(min-width: 768px) 200px, 100vw" :width="200" :height="125" />
                                    @endif
                                </div>
                                <div class="flex min-w-0 flex-col gap-1.5">
                                    <p class="row-title m-0 text-lg font-medium leading-snug text-[var(--text)]">{{ $post->title }}</p>
                                    @if ($post->excerpt)
                                        <p class="body-sm">{{ $post->excerpt }}</p>
                                    @endif
                                    <p class="m-0 mt-0.5 text-[13px] text-[var(--dim)]">{{ collect([$post->category?->name, $post->reading_minutes.' min read'])->filter()->implode(' · ') }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($posts->hasPages())
                <nav class="pagination" aria-label="Pagination">
                    @if ($posts->onFirstPage())
                        <span class="disabled" aria-disabled="true">← Previous</span>
                    @else
                        <a href="{{ $posts->previousPageUrl() }}" rel="prev">← Previous</a>
                    @endif
                    <span class="text-[var(--dim)]">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                    @if ($posts->hasMorePages())
                        <a href="{{ $posts->nextPageUrl() }}" rel="next">Next →</a>
                    @else
                        <span class="disabled" aria-disabled="true">Next →</span>
                    @endif
                </nav>
            @endif
        </main>
        <footer class="page-footer"><x-site.footer /></footer>
    </div>
</x-layouts.site>
