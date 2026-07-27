<?php

namespace Database\Factories;

use App\Models\AccountingClosingChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingClosingChecklistItemFactory extends Factory
{
    protected $model = AccountingClosingChecklistItem::class;

    public function definition(): array
    {
        return ['code' => $this->faker->unique()->lexify('CHECK-????'), 'name_ar' => 'Automated check', 'category' => 'other', 'severity' => 'blocking', 'status' => 'pending', 'is_required' => true, 'is_automated' => true];
    }
}
