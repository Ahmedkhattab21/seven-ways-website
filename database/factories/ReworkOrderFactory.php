<?php

namespace Database\Factories;

use App\Models\ReworkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReworkOrderFactory extends Factory
{
    protected $model = ReworkOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'work_order_id' => 1,
            'rework_number' => fake()->unique()->bothify('RW-######'), 'status' => 'draft',
            'reason_code' => 'other', 'reason' => fake()->sentence(), 'created_by' => 1,
        ];
    }
}
