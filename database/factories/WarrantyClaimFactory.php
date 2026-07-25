<?php

namespace Database\Factories;

use App\Models\WarrantyClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarrantyClaimFactory extends Factory
{
    protected $model = WarrantyClaim::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'claim_number' => fake()->unique()->bothify('WCL-######'),
            'warranty_id' => 1, 'customer_id' => 1, 'vehicle_id' => 1, 'status' => 'submitted',
            'complaint' => fake()->sentence(), 'reported_at' => now(), 'decision' => 'pending',
            'created_by' => 1,
        ];
    }
}
