<?php

namespace Database\Factories;

use App\Models\BankStatementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 1000);

        return ['line_number' => fake()->unique()->numberBetween(1, 100000), 'transaction_date' => now(),
            'description' => fake()->sentence(), 'debit_amount' => 0, 'credit_amount' => $amount,
            'status' => 'unmatched', 'matched_amount' => 0, 'unmatched_amount' => $amount,
            'is_duplicate' => false, 'raw_hash' => hash('sha256', fake()->uuid())];
    }
}
