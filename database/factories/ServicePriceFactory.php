<?php

namespace Database\Factories;

use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePriceFactory extends Factory
{
    protected $model = ServicePrice::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'branch_id' => 1, 'service_id' => 1, 'price' => fake()->randomFloat(4, 50, 2000), 'effective_from' => now()->toDateString(), 'priority' => 0, 'is_active' => true];
    }
}
