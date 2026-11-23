<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Every sluggable model: a blank slug is made from the title (or name) on save, always in the normalised
 * "lowercase-words-with-hyphens" form, with -2, -3… added when it is taken.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            $slug = Str::slug((string) ($model->getAttribute('slug') ?: $model->getAttribute(static::slugSource())));
            $model->setAttribute('slug', static::uniqueSlug($slug !== '' ? $slug : 'item', $model));
        });
    }

    public static function slugSource(): string
    {
        return 'title';
    }

    protected static function uniqueSlug(string $base, Model $model): string
    {
        $slug = $base;
        $n = 2;
        $query = fn (string $candidate) => $model->newQueryWithoutScopes()
            ->where('slug', $candidate)
            ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
            ->exists();
        while ($query($slug)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
