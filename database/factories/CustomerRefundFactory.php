<?php

namespace Database\Factories;

use App\Models\CustomerRefund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerRefundFactory extends Factory
{
    protected $model = CustomerRefund::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'refund_number' => 'REF-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'refund_date' => today(), 'amount' => 25, 'reason' => 'Test refund'];
    }
}
