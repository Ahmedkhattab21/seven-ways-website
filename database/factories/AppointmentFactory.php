<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'appointment_number' => fake()->unique()->bothify('APT-######'),
            'customer_id' => 1, 'vehicle_id' => 1, 'status' => 'pending',
            'scheduled_start' => now()->addDay(), 'scheduled_end' => now()->addDay()->addHour(),
            'estimated_duration_minutes' => 60, 'booking_source' => 'walk_in', 'priority' => 'normal',
            'deposit_required' => false, 'deposit_amount' => 0, 'deposit_status' => 'not_required', 'created_by' => 1,
        ];
    }
}
