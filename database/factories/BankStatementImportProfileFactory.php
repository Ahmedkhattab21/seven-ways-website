<?php

namespace Database\Factories;

use App\Models\BankStatementImportProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankStatementImportProfileFactory extends Factory
{
    protected $model = BankStatementImportProfile::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'format' => 'csv', 'delimiter' => ',', 'encoding' => 'UTF-8',
            'date_format' => 'Y-m-d', 'decimal_separator' => '.', 'has_header' => true,
            'column_mapping' => ['transaction_date' => 'date', 'description' => 'description', 'debit' => 'debit', 'credit' => 'credit'],
            'skip_rows' => 0, 'direction_policy' => 'credit_increases', 'balance_tolerance' => 0,
            'is_default' => false, 'is_active' => true];
    }
}
