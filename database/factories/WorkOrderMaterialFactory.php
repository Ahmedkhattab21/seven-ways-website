<?php

namespace Database\Factories;

use App\Models\WorkOrderMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderMaterialFactory extends Factory
{
    protected $model = WorkOrderMaterial::class;

    public function definition(): array
    {
        return ['work_order_id' => 1, 'product_id' => 1, 'warehouse_id' => 1, 'material_type' => 'quantity', 'expected_quantity' => 1, 'unit_id' => 1, 'status' => 'planned'];
    }
}
