<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Latest edited posts with their status, as in the approved dashboard canvas.
 */
class RecentPosts extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent posts')
            ->query(Post::query()->limit(5))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->weight('medium')
                    ->description(fn (Post $record): string => self::meta($record)),
                TextColumn::make('status')->badge()->alignEnd(),
            ])
            ->recordUrl(fn (Post $record): string => PostResource::getUrl('edit', ['record' => $record]))
            ->headerActions([Action::make('all')->label('View all')->link()->url(PostResource::getUrl('index'))])
            ->emptyStateHeading('No posts yet')
            ->paginated(false);
    }

    private static function meta(Post $post): string
    {
        return match ($post->status) {
            PostStatus::Scheduled => 'Scheduled · '.$post->published_at?->format('j M'),
            PostStatus::Published => 'Published · '.$post->published_at?->format('j M Y'),
            default => 'Draft · edited '.($post->updated_at?->isToday() ? 'today' : $post->updated_at?->diffForHumans()),
        };
    }
}
