<?php

namespace Database\Factories;

use App\Models\AppointmentService;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentServiceFactory extends Factory
{
    protected $model = AppointmentService::class;

    public function definition(): array
    {
        return [
            'appointment_id' => 1, 'service_id' => 1, 'description' => fake()->words(3, true),
            'quantity' => 1, 'estimated_duration_minutes' => 60, 'unit_price_snapshot' => 100,
            'total_snapshot' => 100, 'status' => 'planned', 'sort_order' => 0,
        ];
    }
}
