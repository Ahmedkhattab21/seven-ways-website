<?php

namespace Database\Factories;

use App\Models\SalesInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SalesInvoiceItemFactory extends Factory
{
    protected $model = SalesInvoiceItem::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'item_type' => 'custom', 'description' => $this->faker->words(3, true), 'quantity' => 1, 'unit_price' => 100, 'gross_amount' => 100, 'discount_value' => 0, 'discount_amount' => 0, 'net_amount' => 100, 'tax_rate' => 14, 'tax_amount' => 14, 'total' => 114, 'sort_order' => 0];
    }
}
