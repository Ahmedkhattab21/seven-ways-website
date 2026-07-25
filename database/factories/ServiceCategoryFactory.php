<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'code' => fake()->unique()->bothify('CAT-####'), 'name' => fake()->words(2, true), 'sort_order' => 0, 'is_active' => true];
    }
}
