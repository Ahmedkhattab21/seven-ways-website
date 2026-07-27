<?php

namespace Database\Factories;

use App\Models\EmployeeCommissionAccrual;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeCommissionAccrualFactory extends Factory
{
    protected $model = EmployeeCommissionAccrual::class;

    public function definition(): array
    {
        return [
            'source_key' => hash('sha256', fake()->unique()->uuid()),
            'accrual_date' => now()->toDateString(),
            'basis_amount' => '1000.0000',
            'rule_value' => '5.0000',
            'commission_amount' => '50.0000',
            'settled_amount' => '0.0000',
            'calculation_snapshot' => [],
            'status' => 'calculated',
        ];
    }
}
