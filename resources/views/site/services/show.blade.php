@php
    $profile = app(\App\Settings\ProfileSettings::class);
    $email = $profile->email ?: config('brand.contact_email');
    $path = route('services.show', $service, false);
    $faqItems = $service->faqItems();
    $jsonLd = array_values(array_filter([
        \App\Support\Seo\JsonLd::service($service),
        \App\Support\Seo\JsonLd::breadcrumbs([['Home', '/'], ['Services', '/services'], [$service->title, $path]]),
        \App\Support\Seo\JsonLd::person($profile),
        \App\Support\Seo\JsonLd::faq(\App\Support\Seo\Seo::absolute($path).'#faq-h', $faqItems),
    ]));
    $linkedin = collect($profile->socials)->firstWhere('network', 'linkedin');
    $mailto = 'mailto:'.$email.'?subject='.rawurlencode($service->title);
@endphp
<x-layouts.site :title="$service->seo_title ?: $service->title" :description="$service->seo_description ?: $service->summary" :json-ld="$jsonLd">
    <div class="page-wrap">
        <x-site.topbar current="services" />
    </div>
    <main id="content" class="relative z-[1] px-6 pb-24">
        <div class="cs-col flex flex-col gap-[18px] pt-12 md:pt-16">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="{{ route('services.index') }}">Services</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="text-[var(--muted)]">{{ $service->title }}</li>
                </ol>
            </nav>
            <p class="eyebrow m-0">Service</p>
            <h1 class="cs-title">{{ $service->title }}</h1>
            @if ($service->summary)
                <p class="cs-lead">{{ $service->summary }}</p>
            @endif
        </div>

        <div class="cs-col mt-12 flex flex-col gap-14">
            <div class="prose">{!! $service->body_html !!}</div>

            @if ($projects->isNotEmpty() || $posts->isNotEmpty())
                <section class="related" aria-labelledby="related-work-h">
                    <h2 id="related-work-h" class="related-title">Related work</h2>
                    <ul>
                        @foreach ($projects as $project)
                            <li><a class="row-link" href="{{ route('projects.show', $project) }}"><span class="row-title">{{ $project->title }}</span><span class="row-kind">Case study</span></a></li>
                        @endforeach
                        @foreach ($posts as $post)
                            <li><a class="row-link" href="{{ route('posts.show', $post) }}"><span class="row-title">{{ $post->title }}</span><span class="row-kind">Article</span></a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($faqItems !== [])
                <section class="faq" aria-labelledby="faq-h">
                    <h2 id="faq-h" class="faq-title">Frequently asked questions</h2>
                    @foreach ($faqItems as $item)
                        <div class="faq-item">
                            <h3>{{ $item['question'] }}</h3>
                            <p>{!! nl2br(e($item['answer'])) !!}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            <aside class="cta-box" aria-labelledby="cta-h">
                <h2 id="cta-h" class="cta-title">{{ $service->cta_line ?: 'Have a project in mind?' }}</h2>
                <p class="m-0">Tell me what you need, and I'll reply with next steps.</p>
                <p class="cta-actions">
                    <a class="cta-button" href="{{ $mailto }}">Email me</a>
                    @if ($linkedin)
                        <span>or <a class="cta-alt" href="{{ $linkedin['url'] }}" rel="me noopener" target="_blank">connect on LinkedIn</a></span>
                    @endif
                </p>
            </aside>

            <a class="self-start text-sm text-[var(--muted)]" href="{{ route('services.index') }}">← All services</a>
        </div>
    </main>
</x-layouts.site>
