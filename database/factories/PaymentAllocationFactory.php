<?php

namespace Database\Factories;

use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'amount' => 50, 'allocated_at' => now()];
    }
}
