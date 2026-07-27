<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'account_code' => strtoupper(fake()->unique()->bothify('BA-####')),
            'account_name' => fake()->company().' Account', 'status' => 'draft',
            'account_type' => 'current', 'is_primary' => false,
            'allows_receipts' => true, 'allows_payments' => true,
            'allows_transfers' => true, 'requires_reconciliation' => true,
        ];
    }
}
