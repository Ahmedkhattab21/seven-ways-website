<?php

namespace Database\Factories;

use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        return [
            'line_number' => 1, 'exchange_rate' => 1, 'debit_amount' => 0,
            'credit_amount' => 0, 'base_debit_amount' => 0, 'base_credit_amount' => 0,
            'tax_component' => 'none',
        ];
    }
}
