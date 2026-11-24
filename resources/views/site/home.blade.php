@php
    /** @var \App\Settings\ProfileSettings $profile */
    $sections = collect([
        'about' => 'About',
        'services' => $services->isNotEmpty() ? 'Services' : null,
        'experience' => $experiences->isNotEmpty() ? 'Experience' : null,
        'projects' => $projects->isNotEmpty() ? 'Projects' : null,
        'writing' => $posts->isNotEmpty() ? 'Writing' : null,
    ])->filter();
    $email = $profile->email ?: config('brand.contact_email');
@endphp
<x-layouts.site :description="$profile->tagline" :json-ld="[\App\Support\Seo\JsonLd::person($profile), \App\Support\Seo\JsonLd::website(app(\App\Settings\SeoSettings::class)), \App\Support\Seo\JsonLd::professionalService(app(\App\Settings\SeoSettings::class), $profile, $services)]">
    <div class="cb-wrap">
        <header class="cb-left">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-2.5">
                    <h1 class="hero-name">{{ $profile->name }}</h1>
                    <p class="hero-role">{{ $profile->title }}</p>
                    <p class="hero-intro">{{ $profile->tagline }}</p>
                </div>
                <nav class="section-nav" aria-label="Sections">
                    <ul>
                        @foreach ($sections as $id => $label)
                            <li>
                                <a class="nav-link" href="#{{ $id }}" data-section-link="{{ $id }}">
                                    <span class="nav-rule" aria-hidden="true"></span><span class="nav-label">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            <div class="socials">
                @foreach ($profile->socials as $social)
                    <a class="icon-btn" href="{{ $social['url'] }}" aria-label="{{ ['github' => 'GitHub', 'linkedin' => 'LinkedIn', 'x' => 'X (Twitter)', 'email' => 'Email'][$social['network']] ?? ucfirst($social['network']) }}" @if (str_starts_with($social['url'], 'http')) rel="me noopener" target="_blank" @endif>
                        <x-site.icon :name="$social['network']" />
                    </a>
                @endforeach
                @unless (collect($profile->socials)->contains('network', 'email'))
                    <a class="icon-btn" href="mailto:{{ $email }}" aria-label="Email"><x-site.icon name="email" /></a>
                @endunless
                <span class="divider-v" aria-hidden="true"></span>
                <x-site.theme-toggle />
            </div>
        </header>

        <main id="content" class="cb-right">
            <section id="about" class="section" aria-labelledby="about-h">
                <h2 id="about-h" class="eyebrow">About</h2>
                <div class="prose-about">{!! $profile->about_html !!}</div>
            </section>

            @if ($services->isNotEmpty())
                <section id="services" class="section" aria-labelledby="services-h">
                    <h2 id="services-h" class="eyebrow mb-1">Services</h2>
                    @foreach ($services as $service)
                        <div class="service">
                            <h3>
                                @if ($service->hasPage())
                                    <a class="service-link" href="{{ route('services.show', $service) }}">{{ $service->title }} <span aria-hidden="true">→</span></a>
                                @else
                                    {{ $service->title }}
                                @endif
                            </h3>
                            <p>{{ $service->summary }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            @if ($experiences->isNotEmpty())
                <section id="experience" class="section" aria-labelledby="experience-h">
                    <h2 id="experience-h" class="eyebrow mb-3">Experience</h2>
                    <ol class="m-0 mb-1 flex list-none flex-col gap-1 p-0">
                        @foreach ($experiences as $job)
                            <li class="card cb-row">
                                <p class="date-label">{{ $job->dateRange() }}</p>
                                <div class="flex min-w-0 flex-col gap-2.5">
                                    <h3 class="job-title">
                                        <span class="card-title">{{ $job->position }}</span>
                                        <span class="sep">·</span>
                                        @if ($job->company_url)
                                            <a href="{{ $job->company_url }}" rel="noopener" target="_blank">{{ $job->company }} <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>
                                        @else
                                            <span class="font-normal">{{ $job->company }}</span>
                                        @endif
                                    </h3>
                                    @if ($job->description_html)
                                        <div class="body-sm">{!! $job->description_html !!}</div>
                                    @endif
                                    <x-site.chips :tags="$job->tags" />
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if ($profile->cv_path)
                        <a class="row-link more-link" href="{{ route('cv') }}"><span class="u">View full CV</span><span aria-hidden="true">→</span></a>
                    @endif
                </section>
            @endif

            @if ($projects->isNotEmpty())
                <section id="projects" class="section" aria-labelledby="projects-h">
                    <h2 id="projects-h" class="eyebrow mb-5">Selected projects</h2>
                    <ul class="m-0 flex list-none flex-col gap-10 p-0">
                        @foreach ($projects as $project)
                            @php($cover = $project->getFirstMedia('cover'))
                            <li>
                                <a class="row-link proj card cb-row" href="{{ $project->primaryUrl() ?? route('projects.index') }}" @if ($project->linksExternally()) rel="noopener" target="_blank" @endif>
                                    @if ($cover)
                                        <x-site.media-image :media="$cover" conversion="card" sizes="(min-width: 640px) 150px, 100vw" />
                                    @else
                                        <span aria-hidden="true"></span>
                                    @endif
                                    <div class="flex min-w-0 flex-col gap-2">
                                        @if ($project->metaLine() !== '')
                                            <p class="meta">{{ $project->metaLine() }}</p>
                                        @endif
                                        <h3 class="row-heading card-title">{{ $project->title }}</h3>
                                        <p class="body-sm">{{ $project->summary }}</p>
                                        <p class="cta">
                                            @if ($project->has_case_study)
                                                Read case study <span aria-hidden="true">→</span>
                                            @elseif ($project->linksExternally())
                                                Visit {{ $project->linkDomain() }} <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span>
                                            @endif
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="row-link more-link" href="{{ route('projects.index') }}"><span class="u">View all projects</span><span aria-hidden="true">→</span></a>
                </section>
            @endif

            @if ($posts->isNotEmpty())
                <section id="writing" class="section" aria-labelledby="writing-h">
                    <h2 id="writing-h" class="eyebrow mb-1">Writing</h2>
                    <ul class="m-0 list-none p-0">
                        @foreach ($posts as $post)
                            <li class="post-row">
                                <a class="row-link cb-row" href="{{ route('posts.show', $post) }}">
                                    <p class="date-label m-0"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time></p>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <p class="row-title m-0 text-[17px] font-medium text-[var(--text)]">{{ $post->title }}</p>
                                        <p class="m-0 text-[13px] text-[var(--dim)]">{{ $post->reading_minutes }} min read</p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="row-link more-link" href="{{ route('posts.index') }}"><span class="u">All writing</span><span aria-hidden="true">→</span></a>
                </section>
            @endif

            <section id="contact" class="section" aria-labelledby="contact-h">
                <h2 id="contact-h" class="eyebrow">Contact</h2>
                <p class="contact-statement mt-4">Have a WordPress or Laravel project in mind? Tell me about it.</p>
                <p class="m-0 mt-4"><a class="contact-email" href="mailto:{{ $email }}"><x-site.icon name="email" /><span>{{ $email }}</span></a></p>
            </section>

            <footer class="site-footer">
                <x-site.footer />
            </footer>
        </main>
    </div>
</x-layouts.site>
