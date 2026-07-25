<?php

namespace Database\Factories;

use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'payment_number' => 'PAY-'.$this->faker->unique()->numerify('######'), 'status' => 'approved', 'payment_date' => today(), 'amount' => 100, 'allocated_amount' => 0, 'unallocated_amount' => 100, 'source_type' => 'manual'];
    }
}
