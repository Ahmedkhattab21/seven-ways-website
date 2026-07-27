<?php

namespace Database\Factories;

use App\Models\AccountingAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingAdjustmentFactory extends Factory
{
    protected $model = AccountingAdjustment::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'adjustment_type' => 'accrual', 'reversal_policy' => 'none', 'status' => 'draft'];
    }
}
