<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * @return array<model-property<InvoiceItem>, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'position' => 0,
            'description' => fake()->sentence(4),
            'quantity_milli' => 1000,
            'unit_price_minor' => fake()->numberBetween(1000, 100000),
        ];
    }
}
