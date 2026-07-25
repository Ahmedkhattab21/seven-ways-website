<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'branch_id' => 1, 'warehouse_id' => 1, 'work_order_number' => fake()->unique()->bothify('WO-######'), 'customer_id' => 1, 'vehicle_id' => 1, 'status' => 'awaiting_inspection', 'priority' => 'normal', 'estimated_subtotal' => 0, 'estimated_tax' => 0, 'estimated_total' => 0, 'created_by' => 1];
    }
}
