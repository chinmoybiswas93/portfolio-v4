<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    /**
     * @return array<model-property<Experience>, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 years', '-1 year');

        return [
            'company' => fake()->unique()->company(),
            'company_url' => fake()->url(),
            'position' => fake()->jobTitle(),
            'started_on' => $start->format('Y-m-01'),
            'ended_on' => fake()->dateTimeBetween($start, 'now')->format('Y-m-01'),
            'description_md' => fake()->paragraph(),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function current(): static
    {
        return $this->state(['ended_on' => null]);
    }
}
