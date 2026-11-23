<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\StandardEditLayout;
use App\Filament\Support\SyncsCoverAlt;
use App\Models\Post;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    use StandardEditLayout;
    use SyncsCoverAlt;

    protected static string $resource = PostResource::class;

    protected function previewUrl(): ?string
    {
        /** @var Post $post */
        $post = $this->getRecord();
        $live = $post->published_at !== null && $post->published_at->isPast()
            && in_array($post->status, [PostStatus::Published, PostStatus::Scheduled], true);

        return $live ? route('posts.show', $post) : null;
    }
}
