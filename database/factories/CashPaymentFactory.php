<?php

namespace Database\Factories;

use App\Models\CashPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashPaymentFactory extends Factory
{
    protected $model = CashPayment::class;

    public function definition(): array
    {
        return [
            'document_number' => strtoupper(fake()->unique()->bothify('CP-#####')),
            'payment_type' => 'miscellaneous', 'status' => 'draft',
            'document_date' => now()->toDateString(), 'exchange_rate' => 1,
            'amount' => 100, 'description' => fake()->sentence(),
        ];
    }
}
