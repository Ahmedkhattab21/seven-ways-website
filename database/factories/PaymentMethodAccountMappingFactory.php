<?php

namespace Database\Factories;

use App\Models\PaymentMethodAccountMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodAccountMappingFactory extends Factory
{
    protected $model = PaymentMethodAccountMapping::class;

    public function definition(): array
    {
        return ['is_active' => true];
    }
}
