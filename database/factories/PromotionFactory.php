<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'code' => fake()->unique()->bothify('PRM-####'), 'name' => fake()->words(3, true), 'promotion_type' => 'general', 'discount_type' => 'percentage', 'discount_value' => 10, 'start_at' => now(), 'end_at' => now()->addMonth(), 'is_active' => false];
    }
}
