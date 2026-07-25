<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $plate = strtoupper(fake()->unique()->bothify('### ???'));

        return [
            'company_id' => 1,
            'customer_id' => 1,
            'created_branch_id' => 1,
            'vehicle_brand_id' => 1,
            'vehicle_model_id' => 1,
            'manufacturing_year' => fake()->numberBetween(2015, (int) date('Y') + 1),
            'plate_number' => $plate,
            'normalized_plate_number' => str_replace(' ', '', $plate),
            'vin' => strtoupper(fake()->unique()->bothify('?????????????????')),
            'odometer' => fake()->numberBetween(0, 250000),
            'status' => 'active',
        ];
    }
}
