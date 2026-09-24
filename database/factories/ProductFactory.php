<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' gram',
            'price' => fake()->numberBetween(500_000, 2_000_000),
            'stock' => fake()->numberBetween(1, 10),
        ];
    }
}
