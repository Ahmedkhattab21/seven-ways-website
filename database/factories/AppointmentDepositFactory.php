<?php

namespace Database\Factories;

use App\Models\AppointmentDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentDepositFactory extends Factory
{
    protected $model = AppointmentDeposit::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'appointment_id' => 1,
            'receipt_number' => fake()->unique()->bothify('DEP-######'), 'amount' => 100,
            'payment_method_id' => 1, 'received_at' => now(), 'status' => 'recorded', 'received_by' => 1,
        ];
    }
}
