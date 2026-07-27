<?php

namespace Database\Factories;

use App\Models\BankReconciliationMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationMatchFactory extends Factory
{
    protected $model = BankReconciliationMatch::class;

    public function definition(): array
    {
        return ['match_type' => 'one_to_one', 'status' => 'accepted', 'match_method' => 'manual',
            'matched_amount' => fake()->randomFloat(2, 1, 1000), 'difference_amount' => 0];
    }
}
