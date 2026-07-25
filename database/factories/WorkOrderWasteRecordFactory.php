<?php

namespace Database\Factories;

use App\Models\WorkOrderWasteRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderWasteRecordFactory extends Factory
{
    protected $model = WorkOrderWasteRecord::class;

    public function definition(): array
    {
        return ['work_order_id' => 1, 'quantity' => 1, 'unit_cost' => 1, 'total_cost' => 1, 'reason_code' => 'normal_cutting', 'created_by' => 1];
    }
}
