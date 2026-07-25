<?php

namespace Database\Factories;

use App\Models\ServiceMaterialRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceMaterialRequirementFactory extends Factory
{
    protected $model = ServiceMaterialRequirement::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'service_id' => 1, 'product_id' => 1, 'unit_id' => 1, 'requirement_type' => 'consumable', 'expected_quantity' => 1, 'expected_waste_percentage' => 5, 'is_required' => true];
    }
}
