<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasFaq;
use App\Models\Concerns\HasResponsiveImages;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\RendersMarkdown;
use App\Support\Markdown\ReadingTime;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasFaq;
    use HasResponsiveImages;
    use HasSlug;
    use HasTags;
    use InteractsWithMedia;
    use RendersMarkdown;
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'body_md', 'status', 'published_at',
        'is_featured', 'seo_title', 'seo_description', 'takeaways', 'faq', 'reviewed_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $post): void {
            if ($post->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }
            if ($post->isDirty('body_md')) {
                $post->reading_minutes = ReadingTime::minutes($post->body_md);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'reviewed_at' => 'date',
            'takeaways' => 'array',
            'faq' => 'array',
            'is_featured' => 'boolean',
            'reading_minutes' => 'integer',
        ];
    }

    protected function markdownColumns(): array
    {
        return ['body_md' => 'body_html'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerResponsiveConversions();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('images');
        $this->addMediaCollection('og')->singleFile();
    }

    /**
     * Published posts, plus scheduled posts whose time has come.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereIn('status', [PostStatus::Published, PostStatus::Scheduled])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    /**
     * Key takeaways for the post template (ADR-0010): non-empty lines only.
     *
     * @return list<string>
     */
    public function takeawayList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $line): string => trim(is_array($line) ? (string) ($line['text'] ?? '') : (string) $line),
            (array) $this->takeaways,
        ), fn (string $line): bool => $line !== ''));
    }

    /** The "last reviewed" day, when it is after the publish day (shown as "Updated" and used as dateModified). */
    public function updatedOn(): ?CarbonInterface
    {
        return $this->reviewed_at !== null && $this->published_at !== null
            && $this->reviewed_at->startOfDay()->greaterThan($this->published_at->copy()->startOfDay())
            ? $this->reviewed_at : null;
    }
}
