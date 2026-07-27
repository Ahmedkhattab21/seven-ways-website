<?php

namespace Database\Factories;

use App\Models\CashOverShortAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashOverShortAdjustmentFactory extends Factory
{
    protected $model = CashOverShortAdjustment::class;

    public function definition(): array
    {
        return ['adjustment_type' => 'cash_short', 'amount' => 10, 'status' => 'draft', 'description' => fake()->sentence()];
    }
}
