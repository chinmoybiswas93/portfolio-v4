@php
    $cover = $project->getFirstMedia('cover');
    $gallery = $project->getMedia('gallery');
    $timeline = collect([$project->started_on?->year ?? $project->year, $project->ended_on?->year ?? ($project->started_on ? 'now' : null)])->filter()->unique()->implode(' – ');
    $facts = array_filter([
        'Client' => $project->client_name,
        'Sector' => $project->sector,
        'Role' => $project->role,
        'Timeline' => $timeline,
    ]);
@endphp
<x-layouts.site :title="$project->seo_title ?: $project->title" :description="$project->seo_description ?: $project->summary">
    <div class="page-wrap">
        <x-site.topbar current="projects" />
    </div>
    <main id="content" class="relative z-[1] px-6">
        <div class="cs-col flex flex-col gap-[18px] pt-12 md:pt-16">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="{{ route('projects.index') }}">Projects</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="text-[var(--muted)]">{{ $project->title }}</li>
                </ol>
            </nav>
            <h1 class="cs-title">{{ $project->title }}</h1>
            @if ($project->summary)
                <p class="cs-lead">{{ $project->summary }}</p>
            @endif
        </div>

        @if ($facts !== [] || $project->tags->isNotEmpty())
            <div class="cs-col mt-10">
                @if ($facts !== [])
                    <dl class="facts">
                        @foreach ($facts as $label => $value)
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                <div class="mt-[18px]"><x-site.chips :tags="$project->tags" label="Stack" /></div>
            </div>
        @endif

        @if ($cover)
            <figure class="cs-wide mt-12">
                <x-site.media-image :media="$cover" conversion="hero" sizes="(min-width: 1008px) 960px, 100vw" :width="960" :height="600" loading="eager" fetchpriority="high" class="cs-image" />
            </figure>
        @endif

        <div class="cs-col mt-[72px] flex flex-col gap-14">
            @foreach (['problem' => 'The problem', 'approach' => 'Approach'] as $field => $heading)
                @if ($project->{$field.'_html'})
                    <section class="cs-section" aria-labelledby="{{ $field }}-h">
                        <h2 id="{{ $field }}-h" class="eyebrow">{{ $heading }}</h2>
                        {!! $project->{$field.'_html'} !!}
                    </section>
                @endif
            @endforeach

            @if ($project->results_html || ! empty($project->metrics))
                <section class="cs-section gap-[18px]" aria-labelledby="results-h">
                    <h2 id="results-h" class="eyebrow">Results</h2>
                    @if (! empty($project->metrics))
                        <dl class="metrics">
                            @foreach ($project->metrics as $metric)
                                <div><dt>{{ $metric['label'] ?? '' }}</dt><dd>{{ $metric['value'] ?? '' }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                    {!! $project->results_html !!}
                </section>
            @endif
        </div>

        @if ($gallery->isNotEmpty())
            <div class="cs-wide gallery mt-16">
                @foreach ($gallery as $image)
                    <x-site.media-image :media="$image" conversion="card" sizes="(min-width: 768px) 472px, 100vw" :width="472" :height="295" class="cs-image" />
                @endforeach
            </div>
        @endif

        @if ($project->live_url)
            <div class="cs-col mt-12">
                <a class="more-link mt-0" href="{{ $project->live_url }}" rel="noopener" target="_blank"><span class="u">Visit {{ $project->linkDomain() }}</span><span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>
            </div>
        @endif

        @if ($previous || $next)
            <nav class="cs-col pager mt-20 pb-24" aria-label="More projects">
                @if ($previous)
                    <a href="{{ route('projects.show', $previous) }}"><span class="pager-label">← Previous</span><span class="pager-title">{{ $previous->title }}</span></a>
                @endif
                @if ($next)
                    <a class="next" href="{{ route('projects.show', $next) }}"><span class="pager-label">Next →</span><span class="pager-title">{{ $next->title }}</span></a>
                @endif
            </nav>
        @else
            <div class="pb-24"></div>
        @endif
    </main>
    <div class="page-wrap"><footer class="page-footer"><x-site.footer /></footer></div>
</x-layouts.site>
