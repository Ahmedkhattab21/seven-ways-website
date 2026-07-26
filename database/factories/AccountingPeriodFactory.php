<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        return [
            'period_number' => 1, 'code' => fake()->unique()->lexify('PER-????'),
            'name' => 'الفترة الأولى', 'start_date' => '2030-01-01', 'end_date' => '2030-01-31',
            'status' => 'open', 'is_adjustment_period' => false,
        ];
    }
}
