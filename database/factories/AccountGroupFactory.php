<?php

namespace Database\Factories;

use App\Models\AccountGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountGroupFactory extends Factory
{
    protected $model = AccountGroup::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('GRP-????')), 'name_ar' => fake()->words(2, true),
            'level' => 0, 'is_system' => false, 'is_active' => true,
        ];
    }
}
