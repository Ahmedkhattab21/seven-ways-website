<?php

namespace Database\Factories;

use App\Models\YearEndClosingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class YearEndClosingSettingFactory extends Factory
{
    protected $model = YearEndClosingSetting::class;

    public function definition(): array
    {
        return ['close_revenue_directly_to_retained_earnings' => false, 'create_opening_journal' => true, 'auto_create_next_fiscal_year' => true, 'auto_generate_next_periods' => true, 'lock_year_after_close' => false, 'require_all_reconciliations' => true];
    }
}
