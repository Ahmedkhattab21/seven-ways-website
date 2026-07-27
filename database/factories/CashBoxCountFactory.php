<?php

namespace Database\Factories;

use App\Models\CashBoxCount;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashBoxCountFactory extends Factory
{
    protected $model = CashBoxCount::class;

    public function definition(): array
    {
        return [
            'count_type' => 'closing', 'status' => 'draft', 'counted_total' => 0,
            'book_total' => 0, 'difference' => 0, 'counted_at' => now(),
        ];
    }
}
