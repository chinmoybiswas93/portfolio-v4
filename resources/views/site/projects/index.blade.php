<x-layouts.site title="All projects" description="Every website, theme, plugin and app I have built, newest first.">
    <div class="page-wrap">
        <x-site.topbar current="projects" />
        <main id="content" class="pb-24 pt-8 md:pt-12">
            <a class="back text-[var(--text)] font-semibold" href="{{ route('home') }}"><span aria-hidden="true">←</span> {{ app(\App\Settings\ProfileSettings::class)->name }}</a>
            <h1 class="page-title mt-2">All projects</h1>

            <table class="pj-table">
                <caption class="sr-only">Every project, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">Year</th>
                        <th scope="col">Project</th>
                        <th scope="col" class="hide-sm">Made at</th>
                        <th scope="col" class="hide-sm">Built with</th>
                        <th scope="col">Link</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr class="pj-row">
                            <td class="pj-year">{{ $project->year }}</td>
                            <td class="min-w-[180px]">
                                @if ($project->has_case_study)
                                    <a href="{{ route('projects.show', $project) }}">
                                        <span class="pj-title">{{ $project->title }}</span>
                                        <span class="pj-case">Read case study →</span>
                                    </a>
                                @else
                                    <span class="pj-title">{{ $project->title }}</span>
                                @endif
                            </td>
                            <td class="hide-sm text-[14.5px]">{{ $project->made_at }}</td>
                            <td class="hide-sm"><x-site.chips :tags="$project->tags" label="Built with" /></td>
                            <td class="pj-link">
                                @if ($project->linkDomain())
                                    <a href="{{ $project->live_url ?: $project->repo_url }}" rel="noopener" target="_blank">{{ $project->linkDomain() }} <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </main>
        <footer class="page-footer"><x-site.footer /></footer>
    </div>
</x-layouts.site>
