<?php

namespace Database\Factories;

use App\Models\ApprovalTaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApprovalTaskActionFactory extends Factory
{
    protected $model = ApprovalTaskAction::class;

    public function definition(): array
    {
        return [
            'action' => 'approve', 'correlation_id' => Str::uuid(), 'occurred_at' => now(),
        ];
    }
}
