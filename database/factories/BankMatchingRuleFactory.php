<?php

namespace Database\Factories;

use App\Models\BankMatchingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankMatchingRuleFactory extends Factory
{
    protected $model = BankMatchingRule::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'priority' => 100, 'condition_type' => 'reference_contains',
            'condition_value' => fake()->word(), 'result_type' => 'suggest_match', 'auto_match' => false,
            'minimum_confidence' => 90, 'is_active' => true];
    }
}
