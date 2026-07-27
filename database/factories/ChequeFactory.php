<?php

namespace Database\Factories;

use App\Models\Cheque;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChequeFactory extends Factory
{
    protected $model = Cheque::class;

    public function definition(): array
    {
        return [
            'direction' => 'received', 'cheque_number' => fake()->unique()->numerify('########'),
            'cheque_scope_key' => fake()->unique()->sha256(), 'amount' => 100,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft', 'document_number' => strtoupper(fake()->unique()->bothify('CH-#####')),
        ];
    }
}
