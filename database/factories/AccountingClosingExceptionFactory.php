<?php

namespace Database\Factories;

use App\Models\AccountingClosingException;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingClosingExceptionFactory extends Factory
{
    protected $model = AccountingClosingException::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'exception_type' => 'TEST', 'severity' => 'blocking', 'description' => $this->faker->sentence(), 'status' => 'open'];
    }
}
