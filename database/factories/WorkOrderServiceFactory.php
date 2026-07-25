<?php

namespace Database\Factories;

use App\Models\WorkOrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderServiceFactory extends Factory
{
    protected $model = WorkOrderService::class;

    public function definition(): array
    {
        return ['work_order_id' => 1, 'description' => fake()->words(3, true), 'quantity' => 1, 'status' => 'planned', 'planned_duration_minutes' => 60, 'unit_price_snapshot' => 100, 'total_snapshot' => 100];
    }
}
