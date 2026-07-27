<?php

namespace Database\Factories;

use App\Models\MerchantSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantSettlementFactory extends Factory
{
    protected $model = MerchantSettlement::class;

    public function definition(): array
    {
        return [
            'document_number' => strtoupper(fake()->unique()->bothify('MS-#####')),
            'settlement_reference' => fake()->unique()->uuid(),
            'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->toDateString(),
            'settlement_date' => now()->toDateString(), 'gross_amount' => 100,
            'fees_amount' => 2, 'tax_amount' => 0, 'net_amount' => 98, 'status' => 'draft',
        ];
    }
}
