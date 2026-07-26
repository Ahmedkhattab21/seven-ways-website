<?php

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CC-????')), 'name_ar' => fake()->words(2, true),
            'level' => 0, 'cost_center_type' => 'other', 'is_header' => false, 'is_posting' => true,
            'is_system' => false, 'is_active' => true,
        ];
    }
}
