<?php

namespace Database\Factories;

use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleInspectionFactory extends Factory
{
    protected $model = VehicleInspection::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'branch_id' => 1, 'work_order_id' => 1, 'vehicle_id' => 1, 'inspection_type' => 'check_in', 'status' => 'draft'];
    }
}
