<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'code' => fake()->unique()->bothify('WH-##'),
            'name' => fake()->company(), 'warehouse_type' => 'other', 'is_main' => false, 'is_active' => true,
        ];
    }
}
