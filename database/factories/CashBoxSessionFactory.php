<?php

namespace Database\Factories;

use App\Models\CashBoxSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashBoxSessionFactory extends Factory
{
    protected $model = CashBoxSession::class;

    public function definition(): array
    {
        return [
            'session_number' => strtoupper(fake()->unique()->bothify('CS-#####')),
            'business_date' => now()->toDateString(), 'status' => 'opened', 'active_guard' => 'active',
            'opening_book_balance' => 0, 'opening_counted_balance' => 0, 'opening_difference' => 0,
            'opened_at' => now(),
        ];
    }
}
