<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @phpstan-require-extends Model
 */
trait HasTags
{
    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * @param  list<string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->unique()
            ->values()
            ->all();

        $this->tags()->sync($ids);
    }
}
