<x-layouts.site title="Page not found">
    <div class="page-wrap">
        <x-site.topbar />
        <main id="content" class="not-found">
            <div class="flex max-w-[560px] flex-col gap-4">
                <p class="eyebrow text-[var(--dim)]">Error 404</p>
                <h1 class="page-title">This page doesn’t exist</h1>
                <p class="m-0 text-[17px]">The link may be old or mistyped. Everything on the site is one click away from the home page.</p>
                <div class="mt-4 flex flex-wrap gap-7">
                    <a class="more-link mt-0" href="{{ route('home') }}"><span class="u">Go to the home page</span><span aria-hidden="true">→</span></a>
                    <a class="more-link mt-0" href="{{ route('projects.index') }}"><span class="u">Browse projects</span><span aria-hidden="true">→</span></a>
                </div>
            </div>
        </main>
    </div>
</x-layouts.site>
