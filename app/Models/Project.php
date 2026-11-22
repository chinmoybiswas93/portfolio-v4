<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasResponsiveImages;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\RendersMarkdown;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasResponsiveImages;
    use HasTags;
    use InteractsWithMedia;
    use RendersMarkdown;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'summary', 'client_name', 'sector', 'role', 'made_at', 'year',
        'started_on', 'ended_on', 'problem_md', 'approach_md', 'results_md', 'metrics',
        'live_url', 'repo_url', 'is_featured', 'has_case_study', 'status', 'published_at',
        'sort_order', 'seo_title', 'seo_description',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            if ($project->status === PublishStatus::Published && $project->published_at === null) {
                $project->published_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'metrics' => 'array',
            'started_on' => 'date',
            'ended_on' => 'date',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'has_case_study' => 'boolean',
            'year' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function markdownColumns(): array
    {
        return [
            'problem_md' => 'problem_html',
            'approach_md' => 'approach_html',
            'results_md' => 'results_html',
        ];
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerResponsiveConversions();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('og')->singleFile();
    }

    /** Small label above the title: sector or "made at", then the year. */
    public function metaLine(): string
    {
        return collect([$this->sector ?: $this->made_at, $this->year])->filter()->implode(' · ');
    }

    /** Where the project links to on the site: its case study, else its live site, else its repository. */
    public function primaryUrl(): ?string
    {
        if ($this->has_case_study) {
            return route('projects.show', $this);
        }

        return $this->live_url ?: $this->repo_url;
    }

    public function linksExternally(): bool
    {
        return ! $this->has_case_study && $this->primaryUrl() !== null;
    }

    public function linkDomain(): ?string
    {
        $url = $this->live_url ?: $this->repo_url;
        $host = $url !== null ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published)->orderBy('sort_order');
    }
}
