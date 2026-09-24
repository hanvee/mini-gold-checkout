<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seed data from BRIEF.md §03. All prices are for testing purposes only.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Antam 1 gram', 'price' => 1_500_000, 'stock' => 1],
            ['name' => 'UBS 1 gram', 'price' => 1_450_000, 'stock' => 3],
            ['name' => 'Emasku 0.5 gram', 'price' => 750_000, 'stock' => 0],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['name' => $product['name']], $product);
        }
    }
}
