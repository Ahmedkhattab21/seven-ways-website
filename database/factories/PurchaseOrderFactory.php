<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'purchase_order_number' => 'PO-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'order_date' => today(), 'exchange_rate' => 1, 'payment_terms_days' => 30, 'supplier_name_snapshot' => $this->faker->company(), 'subtotal' => 100, 'discount_value' => 0, 'discount_amount' => 0, 'tax_amount' => 14, 'shipping_amount' => 0, 'other_charges' => 0, 'rounding_amount' => 0, 'total' => 114, 'received_amount' => 0, 'invoiced_amount' => 0];
    }
}
