<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasFaq;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\RendersMarkdown;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use HasFaq;
    use HasSlug;
    use HasTags;
    use RendersMarkdown;
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'summary', 'body_md', 'icon', 'sort_order', 'is_published', 'faq', 'cta_line', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer', 'faq' => 'array'];
    }

    protected function markdownColumns(): array
    {
        return ['body_md' => 'body_html'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order');
    }

    /** A published service gets its own page once it has details to show (ADR-0010). */
    public function hasPage(): bool
    {
        return $this->is_published && trim((string) $this->body_md) !== '';
    }

    /**
     * Published services with their own page.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithPage(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('body_md')->where('body_md', '!=', '')->orderBy('sort_order');
    }
}
