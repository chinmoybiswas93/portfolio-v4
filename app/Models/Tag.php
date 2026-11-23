<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use HasSlug;
    use SoftDeletes;

    protected $fillable = ['name', 'slug'];

    /** One shared pool: "WordPress", "wordpress" and "Word Press" all resolve to the same tag. */
    public static function findOrCreateByName(string $name): self
    {
        // A trashed tag with the same slug is brought back rather than colliding with the unique slug.
        $tag = self::withTrashed()->firstOrCreate(['slug' => Str::slug($name)], ['name' => trim($name)]);
        if ($tag->trashed()) {
            $tag->restore();
        }

        return $tag;
    }

    /**
     * @return MorphToMany<Post, $this>
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /**
     * @return MorphToMany<Project, $this>
     */
    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }

    /**
     * @return MorphToMany<Experience, $this>
     */
    public function experiences(): MorphToMany
    {
        return $this->morphedByMany(Experience::class, 'taggable');
    }

    public static function slugSource(): string
    {
        return 'name';
    }
}
