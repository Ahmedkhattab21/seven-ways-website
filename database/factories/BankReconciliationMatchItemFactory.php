<?php

namespace Database\Factories;

use App\Models\BankReconciliationMatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationMatchItemFactory extends Factory
{
    protected $model = BankReconciliationMatchItem::class;

    public function definition(): array
    {
        return ['side' => 'statement', 'allocated_amount' => fake()->randomFloat(2, 1, 1000)];
    }
}
