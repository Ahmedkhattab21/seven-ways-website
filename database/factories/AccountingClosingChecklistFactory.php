<?php

namespace Database\Factories;

use App\Models\AccountingClosingChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingClosingChecklistFactory extends Factory
{
    protected $model = AccountingClosingChecklist::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'checklist_type' => 'period', 'status' => 'pending', 'completed_items' => 0, 'total_items' => 0];
    }
}
