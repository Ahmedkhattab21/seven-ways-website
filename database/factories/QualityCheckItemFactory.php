<?php

namespace Database\Factories;

use App\Models\QualityCheckItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityCheckItemFactory extends Factory
{
    protected $model = QualityCheckItem::class;

    public function definition(): array
    {
        return [
            'quality_check_id' => 1, 'code' => fake()->unique()->bothify('CHK-###'),
            'name' => fake()->sentence(3), 'category' => 'finish', 'check_type' => 'pass_fail',
            'is_required' => true, 'is_critical' => false, 'result' => 'pending',
            'photo_required' => false,
        ];
    }
}
