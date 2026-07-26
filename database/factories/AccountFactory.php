<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'account_code' => (string) fake()->unique()->numberBetween(700000, 999999),
            'name_ar' => fake()->words(2, true), 'account_level' => 0,
            'is_header' => false, 'is_posting' => true, 'normal_balance' => 'debit',
            'allows_multi_currency' => false, 'is_system' => false, 'is_active' => true,
            'allow_manual_entry' => true,
        ];
    }
}
