<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders each Markdown source column to its HTML column whenever the source changes.
 *
 * @phpstan-require-extends Model
 */
trait RendersMarkdown
{
    /**
     * Map of Markdown source column => rendered HTML column.
     *
     * @return array<string, string>
     */
    abstract protected function markdownColumns(): array;

    public static function bootRendersMarkdown(): void
    {
        static::saving(function (Model $model): void {
            /** @var Model&self $model */
            $renderer = app(MarkdownRenderer::class);

            foreach ($model->markdownColumns() as $source => $target) {
                if ($model->isDirty($source) || ($model->getAttribute($target) === null && $model->getAttribute($source) !== null)) {
                    $model->setAttribute($target, $renderer->render($model->getAttribute($source)));
                }
            }
        });
    }
}
