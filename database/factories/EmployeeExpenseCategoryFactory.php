<?php

namespace Database\Factories;

use App\Models\EmployeeExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeExpenseCategoryFactory extends Factory
{
    protected $model = EmployeeExpenseCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EXP-###')),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
