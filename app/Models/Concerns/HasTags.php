<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\TagType;
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
    public function syncTagNames(array $names, TagType $type): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): int => Tag::findOrCreateByName($name, $type)->id)
            ->unique()
            ->values()
            ->all();

        $this->tags()->sync($ids);
    }
}
