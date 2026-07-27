<?php

namespace Database\Factories;

use App\Models\ApprovalTask;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApprovalTaskFactory extends Factory
{
    protected $model = ApprovalTask::class;

    public function definition(): array
    {
        return [
            'module' => 'purchasing', 'document_type' => 'PurchaseRequisition',
            'stage' => 'approval', 'status' => 'pending',
            'requested_at' => now(), 'required_permission' => 'purchase_requisitions.approve',
            'priority' => 'normal', 'idempotency_key' => Str::uuid(),
        ];
    }
}
