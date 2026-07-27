<?php

namespace Database\Factories;

use App\Models\EmployeeExpenseClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeExpenseClaimFactory extends Factory
{
    protected $model = EmployeeExpenseClaim::class;

    public function definition(): array
    {
        return [
            'claim_number' => strtoupper(fake()->unique()->bothify('EEC-#####')),
            'claim_date' => now()->toDateString(),
            'business_purpose' => fake()->sentence(),
            'subtotal' => '100.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '100.0000',
            'status' => 'draft',
        ];
    }
}
