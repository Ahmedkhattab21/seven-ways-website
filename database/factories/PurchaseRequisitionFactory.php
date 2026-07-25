<?php

namespace Database\Factories;

use App\Models\PurchaseRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseRequisitionFactory extends Factory
{
    protected $model = PurchaseRequisition::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'requisition_number' => 'PR-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'request_date' => today(), 'priority' => 'normal', 'purpose' => $this->faker->sentence(), 'estimated_total' => 0];
    }
}
