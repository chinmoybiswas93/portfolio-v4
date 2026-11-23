<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * @return array<model-property<Redirect>, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(),
            'to_path' => '/'.fake()->slug(),
            'status_code' => 301,
        ];
    }
}
