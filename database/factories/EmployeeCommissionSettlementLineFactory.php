<?php

namespace Database\Factories;

use App\Models\EmployeeCommissionSettlementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeCommissionSettlementLineFactory extends Factory
{
    protected $model = EmployeeCommissionSettlementLine::class;

    public function definition(): array
    {
        return ['amount' => '50.0000'];
    }
}
