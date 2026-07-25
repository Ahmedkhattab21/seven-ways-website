<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'code' => fake()->unique()->bothify('PKG-####'), 'name' => fake()->words(3, true), 'package_type' => 'fixed', 'is_active' => true];
    }
}
