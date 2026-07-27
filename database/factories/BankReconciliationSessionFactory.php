<?php

namespace Database\Factories;

use App\Models\BankReconciliationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationSessionFactory extends Factory
{
    protected $model = BankReconciliationSession::class;

    public function definition(): array
    {
        return ['session_number' => 'BR-'.fake()->unique()->numerify('######'), 'date_from' => now()->startOfMonth(),
            'date_to' => now()->endOfMonth(), 'statement_opening_balance' => 0, 'statement_closing_balance' => 0,
            'book_opening_balance' => 0, 'book_closing_balance' => 0, 'tolerance' => 0,
            'status' => 'matching', 'started_at' => now()];
    }
}
