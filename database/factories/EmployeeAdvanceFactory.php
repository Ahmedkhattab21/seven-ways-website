<?php

namespace Database\Factories;

use App\Models\EmployeeAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeAdvanceFactory extends Factory
{
    protected $model = EmployeeAdvance::class;

    public function definition(): array
    {
        return [
            'advance_number' => strtoupper(fake()->unique()->bothify('EAD-#####')),
            'advance_type' => 'advance',
            'request_date' => now()->toDateString(),
            'purpose' => fake()->sentence(),
            'amount' => '1000.0000',
            'settled_amount' => '0.0000',
            'status' => 'draft',
        ];
    }
}
