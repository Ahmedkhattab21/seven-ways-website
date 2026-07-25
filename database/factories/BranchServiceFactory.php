<?php

namespace Database\Factories;

use App\Models\BranchService;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchServiceFactory extends Factory
{
    protected $model = BranchService::class;

    public function definition(): array
    {
        return ['company_id' => 1, 'branch_id' => 1, 'service_id' => 1, 'is_available' => true, 'booking_enabled' => false, 'requires_approval' => false, 'is_active' => true];
    }
}
