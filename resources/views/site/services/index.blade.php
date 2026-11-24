<x-layouts.site title="Services" :description="'What I do: '.$services->pluck('title')->implode(', ').'.'"
    :json-ld="[\App\Support\Seo\JsonLd::collection('Services', '/services', $services->filter->hasPage()->map(fn ($s): array => [$s->title, route('services.show', $s, false)])->values()->all())]">
    <div class="page-wrap">
        <x-site.topbar current="services" />
        <main id="content" class="flex flex-col gap-10 pb-24 pt-8 md:pt-12">
            <div class="flex flex-col gap-3">
                <h1 class="page-title">Services</h1>
                <p class="m-0 max-w-[560px]">What I can help with, and how I work.</p>
            </div>
            <ul class="services-list">
                @foreach ($services as $service)
                    <li class="service">
                        <h2>
                            @if ($service->hasPage())
                                <a href="{{ route('services.show', $service) }}">{{ $service->title }} <span aria-hidden="true">→</span></a>
                            @else
                                {{ $service->title }}
                            @endif
                        </h2>
                        <p>{{ $service->summary }}</p>
                    </li>
                @endforeach
            </ul>
        </main>
    </div>
</x-layouts.site>
