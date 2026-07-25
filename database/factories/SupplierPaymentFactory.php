<?php

namespace Database\Factories;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'payment_number' => 'SPAY-'.$this->faker->unique()->numerify('######'), 'status' => 'processed', 'payment_date' => today(), 'amount' => 100, 'allocated_amount' => 0, 'unallocated_amount' => 100];
    }
}
