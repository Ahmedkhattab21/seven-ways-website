<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'transfer_number' => fake()->unique()->bothify('TRF-######'),
            'transfer_type' => 'internal', 'from_branch_id' => 1, 'from_warehouse_id' => 1,
            'to_branch_id' => 1, 'to_warehouse_id' => 2, 'status' => 'draft',
            'requested_by' => 1, 'requested_at' => now(),
        ];
    }
}
