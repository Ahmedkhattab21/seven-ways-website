<?php

namespace Database\Factories;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'module' => 'purchasing', 'document_type' => 'PurchaseRequisition',
            'version' => 1, 'is_active' => true,
        ];
    }
}
