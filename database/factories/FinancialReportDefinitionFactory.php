<?php

namespace Database\Factories;

use App\Models\FinancialReportDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialReportDefinitionFactory extends Factory
{
    protected $model = FinancialReportDefinition::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('REP-####'), 'name_ar' => fake()->words(3, true), 'report_type' => 'custom', 'is_system' => false, 'is_active' => true];
    }
}
