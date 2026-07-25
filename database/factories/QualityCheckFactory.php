<?php

namespace Database\Factories;

use App\Models\QualityCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityCheckFactory extends Factory
{
    protected $model = QualityCheck::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'work_order_id' => 1,
            'quality_check_number' => fake()->unique()->bothify('QC-######'),
            'round_number' => 1, 'status' => 'in_progress', 'checked_by' => 1,
            'started_at' => now(), 'requires_rework' => false,
        ];
    }
}
