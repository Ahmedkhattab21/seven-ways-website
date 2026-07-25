<?php

namespace Database\Factories;

use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferItemFactory extends Factory
{
    protected $model = StockTransferItem::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => 1, 'product_id' => 1, 'item_type' => 'quantity',
            'requested_quantity' => 1, 'received_quantity' => 0, 'rejected_quantity' => 0,
            'damaged_quantity' => 0, 'shortage_quantity' => 0, 'unit_cost' => 0, 'total_cost' => 0,
        ];
    }
}
