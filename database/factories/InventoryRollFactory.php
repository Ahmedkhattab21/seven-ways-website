<?php

namespace Database\Factories;

use App\Models\InventoryRoll;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryRollFactory extends Factory
{
    protected $model = InventoryRoll::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'warehouse_id' => 1, 'product_id' => 1,
            'roll_number' => fake()->unique()->bothify('ROLL-#####'), 'width' => 1.5,
            'original_length' => 30, 'remaining_length' => 30, 'original_area' => 45,
            'remaining_area' => 45, 'unit_cost_per_area' => 10, 'total_cost' => 450,
            'received_at' => now(), 'status' => 'available', 'created_by' => 1,
        ];
    }
}
