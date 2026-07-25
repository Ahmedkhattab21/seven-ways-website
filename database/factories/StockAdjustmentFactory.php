<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'warehouse_id' => 1,
            'document_number' => fake()->unique()->bothify('ADJ-######'), 'adjustment_type' => 'increase',
            'status' => 'draft', 'adjustment_date' => today(), 'reason' => 'Test adjustment', 'created_by' => 1,
        ];
    }
}
