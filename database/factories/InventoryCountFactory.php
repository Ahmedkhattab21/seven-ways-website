<?php

namespace Database\Factories;

use App\Models\InventoryCount;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryCountFactory extends Factory
{
    protected $model = InventoryCount::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'warehouse_id' => 1,
            'document_number' => fake()->unique()->bothify('COUNT-######'), 'status' => 'draft',
            'count_date' => today(), 'scope_type' => 'full', 'created_by' => 1,
        ];
    }
}
