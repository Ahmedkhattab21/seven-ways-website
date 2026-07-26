<?php

namespace Database\Factories;

use App\Models\OpeningBalanceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpeningBalanceLineFactory extends Factory
{
    protected $model = OpeningBalanceLine::class;

    public function definition(): array
    {
        return [
            'exchange_rate' => 1, 'debit_amount' => 100, 'credit_amount' => 0, 'sort_order' => 1,
        ];
    }
}
