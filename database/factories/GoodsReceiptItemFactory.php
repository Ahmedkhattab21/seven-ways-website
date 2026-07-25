<?php

namespace Database\Factories;

use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GoodsReceiptItemFactory extends Factory
{
    protected $model = GoodsReceiptItem::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'conversion_factor' => 1, 'received_quantity' => 5, 'accepted_quantity' => 5, 'rejected_quantity' => 0, 'free_quantity' => 0, 'unit_cost' => 10, 'tax_rate' => 15, 'total_cost' => 50, 'condition' => 'good'];
    }
}
