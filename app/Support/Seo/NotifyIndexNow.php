<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\PostStatus;
use App\Enums\PublishStatus;
use App\Jobs\SubmitToIndexNow;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Model observer (ADR-0010): when a live post, case study or service page is saved, queue an IndexNow ping for its page and the
 * listing it appears on. Only in production with a key, and at most once per URL every 10 minutes.
 */
final class NotifyIndexNow
{
    public const int DEDUPE_SECONDS = 600;

    public function saved(Model $model): void
    {
        if (! Seo::indexable() || (string) config('seo.indexnow_key') === '') {
            return;
        }

        $paths = match (true) {
            $model instanceof Post && self::postIsLive($model) => [route('posts.show', $model, false), '/blog'],
            $model instanceof Project && $model->status === PublishStatus::Published && $model->has_case_study => [route('projects.show', $model, false), '/projects'],
            $model instanceof Service && $model->hasPage() => [route('services.show', $model, false), '/services'],
            default => [],
        };

        $urls = array_values(array_filter(
            array_map(fn (string $path): string => Seo::absolute($path), $paths),
            fn (string $url): bool => Cache::add('indexnow:'.hash('sha256', $url), true, self::DEDUPE_SECONDS),
        ));

        if ($urls !== []) {
            SubmitToIndexNow::dispatch($urls)->afterCommit();
        }
    }

    private static function postIsLive(Post $post): bool
    {
        return in_array($post->status, [PostStatus::Published, PostStatus::Scheduled], true)
            && $post->published_at !== null && $post->published_at->isPast();
    }
}
