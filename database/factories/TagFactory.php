<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<model-property<Tag>, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['name' => Str::title($name), 'slug' => Str::slug($name), 'type' => TagType::Topic];
    }
}
