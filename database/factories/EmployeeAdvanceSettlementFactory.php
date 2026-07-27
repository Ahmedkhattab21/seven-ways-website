<?php

namespace Database\Factories;

use App\Models\EmployeeAdvanceSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeAdvanceSettlementFactory extends Factory
{
    protected $model = EmployeeAdvanceSettlement::class;

    public function definition(): array
    {
        return [
            'settlement_type' => 'cash_return',
            'settlement_date' => now()->toDateString(),
            'amount' => '100.0000',
            'status' => 'posted',
        ];
    }
}
