<?php

namespace Database\Factories;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowStepFactory extends Factory
{
    protected $model = ApprovalWorkflowStep::class;

    public function definition(): array
    {
        return [
            'workflow_id' => ApprovalWorkflow::factory(), 'step_order' => 1,
            'required_permission' => 'purchase_requisitions.approve',
            'minimum_approvals' => 1, 'enforce_sod' => true,
        ];
    }
}
