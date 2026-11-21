<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTags;
use App\Models\Concerns\RendersMarkdown;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    use HasTags;
    use RendersMarkdown;

    protected $fillable = [
        'company', 'company_url', 'position', 'location', 'started_on', 'ended_on',
        'description_md', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function markdownColumns(): array
    {
        return ['description_md' => 'description_html'];
    }

    public function isCurrent(): bool
    {
        return $this->ended_on === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->orderByDesc('started_on');
    }
}
