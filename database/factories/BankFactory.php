<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('BNK-###'));

        return [
            'scope_key' => 'factory:'.$code, 'code' => $code, 'name_ar' => fake()->company(),
            'name_en' => fake()->company(), 'is_system' => false, 'is_active' => true,
        ];
    }
}
