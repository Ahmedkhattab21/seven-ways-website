<?php

namespace Database\Factories;

use App\Models\CashBox;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashBoxFactory extends Factory
{
    protected $model = CashBox::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CB-####')), 'name' => fake()->word().' Cash Box',
            'status' => 'draft', 'is_primary' => false, 'allows_receipts' => true,
            'allows_payments' => true, 'requires_shift_opening' => false,
        ];
    }
}
