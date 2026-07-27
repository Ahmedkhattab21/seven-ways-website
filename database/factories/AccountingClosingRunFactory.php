<?php

namespace Database\Factories;

use App\Models\AccountingClosingRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingClosingRunFactory extends Factory
{
    protected $model = AccountingClosingRun::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'closing_type' => 'period_soft_close', 'run_number' => 'CL-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'active_key' => 'active', 'started_at' => now()];
    }
}
