<?php

namespace Database\Factories;

use App\Models\ApprovalDelegation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    public function definition(): array
    {
        return [
            'modules' => ['purchasing'], 'starts_at' => now(), 'ends_at' => now()->addWeek(),
            'reason' => 'Temporary coverage', 'status' => 'active',
        ];
    }
}
