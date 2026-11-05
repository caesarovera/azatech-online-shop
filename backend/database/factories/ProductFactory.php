<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => 'P'.fake()->unique()->numerify('#####'),
            'product_name' => ucwords(fake()->words(2, true)).' '.fake()->randomElement(['150ml', '250ml', '1L', '500g', '1kg', 'pcs']),
            'stock' => fake()->numberBetween(20, 200),
            'price' => fake()->randomElement([5000, 7500, 12000, 15500, 23000, 45000, 99000]).'.00',
            'low_stock_threshold' => 5,
        ];
    }

    public function withStock(int $stock): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }
}
