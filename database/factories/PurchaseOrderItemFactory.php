<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'description' => $this->faker->words(3, true), 'conversion_factor' => 1, 'ordered_quantity' => 10, 'received_quantity' => 0, 'returned_quantity' => 0, 'invoiced_quantity' => 0, 'unit_price' => 10, 'gross_amount' => 100, 'discount_value' => 0, 'discount_amount' => 0, 'net_amount' => 100, 'tax_rate' => 14, 'tax_amount' => 14, 'total' => 114, 'batch_required' => false, 'expiry_required' => false];
    }
}
