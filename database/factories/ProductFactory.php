<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'category_id' => 1, 'sku' => fake()->unique()->bothify('PRD-####'),
            'name' => fake()->words(3, true), 'product_type' => 'consumable', 'tracking_type' => 'quantity',
            'purchase_unit_id' => 1, 'stock_unit_id' => 1, 'sale_unit_id' => 1,
            'costing_method' => 'weighted_average', 'minimum_stock' => 0,
            'is_sellable' => true, 'is_purchasable' => true, 'is_consumable' => true, 'is_active' => true,
        ];
    }
}
