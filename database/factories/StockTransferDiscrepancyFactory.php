<?php

namespace Database\Factories;

use App\Models\StockTransferDiscrepancy;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferDiscrepancyFactory extends Factory
{
    protected $model = StockTransferDiscrepancy::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => 1, 'stock_transfer_item_id' => 1,
            'discrepancy_type' => 'shortage', 'quantity' => 1,
            'description' => fake()->sentence(), 'reported_by' => 1, 'status' => 'open',
        ];
    }
}
