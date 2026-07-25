<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'service_category_id' => 1, 'code' => fake()->unique()->bothify('SRV-####'), 'name' => fake()->words(3, true), 'service_type' => 'ppf', 'pricing_type' => 'fixed', 'default_duration_minutes' => 60, 'requires_vehicle' => true, 'is_active' => true];
    }
}
