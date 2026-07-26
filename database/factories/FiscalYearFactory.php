<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2030, 2090);

        return [
            'code' => 'FY-'.$year, 'name' => 'السنة المالية '.$year,
            'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31",
            'status' => 'draft', 'is_current' => false,
        ];
    }
}
