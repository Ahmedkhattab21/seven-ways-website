<?php

namespace Database\Factories;

use App\Models\CashReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashReceiptFactory extends Factory
{
    protected $model = CashReceipt::class;

    public function definition(): array
    {
        return [
            'document_number' => strtoupper(fake()->unique()->bothify('CR-#####')),
            'receipt_type' => 'miscellaneous', 'status' => 'draft',
            'document_date' => now()->toDateString(), 'exchange_rate' => 1,
            'amount' => 100, 'description' => fake()->sentence(),
        ];
    }
}
