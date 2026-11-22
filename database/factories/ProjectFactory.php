<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<model-property<Project>, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(12),
            'made_at' => 'Personal',
            'year' => fake()->numberBetween(2018, 2026),
            'live_url' => fake()->url(),
            'is_featured' => false,
            'has_case_study' => false,
            'status' => PublishStatus::Published,
            'published_at' => now(),
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PublishStatus::Draft, 'published_at' => null]);
    }

    public function caseStudy(): static
    {
        return $this->state([
            'has_case_study' => true,
            'problem_md' => fake()->paragraph(),
            'approach_md' => fake()->paragraph(),
            'results_md' => fake()->paragraph(),
        ]);
    }
}
