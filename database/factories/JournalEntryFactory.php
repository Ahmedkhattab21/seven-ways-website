<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'journal_number' => strtoupper(fake()->unique()->bothify('JE-####-????')),
            'entry_type' => 'manual', 'status' => 'draft', 'entry_date' => now()->toDateString(),
            'exchange_rate' => 1, 'description' => fake()->sentence(),
            'total_debit' => 0, 'total_credit' => 0, 'base_total_debit' => 0, 'base_total_credit' => 0,
            'is_automatic' => false, 'is_reversal' => false, 'is_opening' => false, 'is_adjusting' => false,
        ];
    }
}
