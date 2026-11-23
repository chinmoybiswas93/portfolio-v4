<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PublishStatus;
use App\Models\Project;
use Illuminate\Contracts\View\View;

final class ProjectController
{
    public function index(): View
    {
        return view('site.projects.index', [
            'projects' => Project::query()->published()->reorder()->orderByDesc('year')->orderBy('sort_order')->with('tags')->get(),
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->status === PublishStatus::Published && $project->has_case_study, 404);
        $project->load(['tags', 'media']);

        $caseStudies = Project::query()->published()->where('has_case_study', true)->get(['id', 'title', 'slug', 'sort_order']);
        $index = $caseStudies->search(fn (Project $p): bool => $p->is($project));

        return view('site.projects.show', [
            'project' => $project,
            'previous' => $index === false ? null : $caseStudies->get($index - 1),
            'next' => $index === false ? null : $caseStudies->get($index + 1),
        ]);
    }
}
