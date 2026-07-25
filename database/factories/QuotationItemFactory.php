<?php

namespace Database\Factories;

use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        return [
            'quotation_id' => 1, 'item_type' => 'custom', 'description' => fake()->words(3, true),
            'quantity' => 1, 'unit_price' => 100, 'gross_amount' => 100, 'discount_value' => 0,
            'discount_amount' => 0, 'net_amount' => 100, 'tax_rate' => 0, 'tax_amount' => 0,
            'total' => 100, 'price_source' => 'manual', 'sort_order' => 0,
        ];
    }
}
