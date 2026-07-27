<?php

namespace Database\Factories;

use App\Models\TreasuryApprovalLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasuryApprovalLimitFactory extends Factory
{
    protected $model = TreasuryApprovalLimit::class;

    public function definition(): array
    {
        return [
            'operation_type' => 'treasury_transfer', 'minimum_amount' => 0,
            'maximum_amount' => 10000, 'approval_level' => 1, 'can_create' => true,
            'can_submit' => true, 'can_approve' => true, 'can_post' => true,
            'is_active' => true, 'valid_from' => now()->toDateString(),
        ];
    }
}
