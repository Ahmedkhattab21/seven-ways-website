<?php

namespace Database\Factories;

use App\Models\CashFlowMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashFlowMappingFactory extends Factory
{
    protected $model = CashFlowMapping::class;

    public function definition(): array
    {
        return ['cash_flow_category' => 'operating', 'cash_flow_line' => 'Operations', 'is_active' => true];
    }
}
