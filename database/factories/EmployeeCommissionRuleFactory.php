<?php

namespace Database\Factories;

use App\Models\EmployeeCommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeCommissionRuleFactory extends Factory
{
    protected $model = EmployeeCommissionRule::class;

    public function definition(): array
    {
        return [
            'rule_type' => 'percentage_net_sales',
            'rule_value' => '5.0000',
            'effective_from' => now()->startOfYear()->toDateString(),
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
