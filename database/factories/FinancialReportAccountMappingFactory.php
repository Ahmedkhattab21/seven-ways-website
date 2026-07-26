<?php

namespace Database\Factories;

use App\Models\FinancialReportAccountMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialReportAccountMappingFactory extends Factory
{
    protected $model = FinancialReportAccountMapping::class;

    public function definition(): array
    {
        return ['sign_multiplier' => 1];
    }
}
