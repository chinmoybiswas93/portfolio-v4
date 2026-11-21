<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TagType;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type'];

    protected function casts(): array
    {
        return ['type' => TagType::class];
    }

    public static function findOrCreateByName(string $name, TagType $type): self
    {
        return self::query()->firstOrCreate(
            ['slug' => Str::slug($name), 'type' => $type],
            ['name' => $name],
        );
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
}
