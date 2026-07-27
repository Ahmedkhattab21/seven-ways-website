<?php

namespace Database\Factories;

use App\Models\TreasuryTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasuryTransferFactory extends Factory
{
    protected $model = TreasuryTransfer::class;

    public function definition(): array
    {
        return [
            'document_number' => strtoupper(fake()->unique()->bothify('TR-####')),
            'from_type' => 'bank', 'to_type' => 'cash_box', 'exchange_rate' => 1,
            'amount' => 100, 'fees_amount' => 0, 'status' => 'draft',
            'transfer_date' => now()->toDateString(),
        ];
    }
}
