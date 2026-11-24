<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PublishStatus;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service landing pages (ADR-0010, slice 8d). A published service gets a page once it has details; related work is
 * the case studies and posts that share its tags.
 */
final class ServiceController
{
    public const int RELATED_LIMIT = 4;

    public function index(): View
    {
        return view('site.services.index', [
            'services' => Service::query()->published()->get(),
        ]);
    }

    public function show(Service $service): View
    {
        abort_unless($service->hasPage(), 404);
        $service->load('tags');
        $tagIds = $service->tags->modelKeys();

        $sharesTags = fn (Builder $query): Builder => $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds));

        return view('site.services.show', [
            'service' => $service,
            'projects' => $tagIds === [] ? collect() : $sharesTags(Project::query())
                ->where('status', PublishStatus::Published)->where('has_case_study', true)
                ->orderBy('sort_order')->limit(self::RELATED_LIMIT)->get(['id', 'title', 'slug', 'summary']),
            'posts' => $tagIds === [] ? collect() : $sharesTags(Post::query()->published())
                ->limit(self::RELATED_LIMIT)->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
        ]);
    }
}
