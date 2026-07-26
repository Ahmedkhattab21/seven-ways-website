<?php

namespace Database\Factories;

use App\Models\FinancialReportSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialReportSectionFactory extends Factory
{
    protected $model = FinancialReportSection::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('SEC-####'), 'name_ar' => fake()->words(2, true), 'section_type' => 'detail', 'sign_multiplier' => 1, 'sort_order' => 1, 'is_total' => false];
    }
}
