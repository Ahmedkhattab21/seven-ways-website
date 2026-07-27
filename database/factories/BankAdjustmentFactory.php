<?php

namespace Database\Factories;

use App\Models\BankAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAdjustmentFactory extends Factory
{
    protected $model = BankAdjustment::class;

    public function definition(): array
    {
        return ['document_number' => 'BA-'.fake()->unique()->numerify('######'), 'adjustment_type' => 'bank_fee',
            'status' => 'draft', 'adjustment_date' => now(), 'exchange_rate' => 1,
            'amount' => fake()->randomFloat(2, 1, 500), 'description' => fake()->sentence()];
    }
}
