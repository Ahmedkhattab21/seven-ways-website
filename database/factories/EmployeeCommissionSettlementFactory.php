<?php

namespace Database\Factories;

use App\Models\EmployeeCommissionSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeCommissionSettlementFactory extends Factory
{
    protected $model = EmployeeCommissionSettlement::class;

    public function definition(): array
    {
        return [
            'settlement_number' => strtoupper(fake()->unique()->bothify('ECS-#####')),
            'settlement_date' => now()->toDateString(),
            'total_amount' => '50.0000',
            'status' => 'draft',
        ];
    }
}
