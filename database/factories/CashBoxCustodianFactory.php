<?php

namespace Database\Factories;

use App\Models\CashBoxCustodian;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashBoxCustodianFactory extends Factory
{
    protected $model = CashBoxCustodian::class;

    public function definition(): array
    {
        return [
            'valid_from' => now()->toDateString(), 'can_receive' => true, 'can_pay' => true,
            'can_transfer' => false, 'is_primary' => false, 'is_active' => true,
        ];
    }
}
