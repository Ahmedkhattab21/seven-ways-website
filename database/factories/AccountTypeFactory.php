<?php

namespace Database\Factories;

use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    public function definition(): array
    {
        return [
            'company_id' => null, 'code' => strtoupper(fake()->unique()->lexify('TYPE-????')),
            'name_ar' => fake()->words(2, true), 'name_en' => fake()->words(2, true),
            'classification' => 'asset', 'normal_balance' => 'debit',
            'statement_type' => 'balance_sheet', 'cash_flow_category' => 'none',
            'is_system' => false, 'is_active' => true,
        ];
    }
}
