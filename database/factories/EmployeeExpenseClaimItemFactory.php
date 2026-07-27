<?php

namespace Database\Factories;

use App\Models\EmployeeExpenseClaimItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeExpenseClaimItemFactory extends Factory
{
    protected $model = EmployeeExpenseClaimItem::class;

    public function definition(): array
    {
        return [
            'expense_date' => now()->toDateString(),
            'description' => fake()->sentence(),
            'net_amount' => '100.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '100.0000',
            'sort_order' => 1,
        ];
    }
}
