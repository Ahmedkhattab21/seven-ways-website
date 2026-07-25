<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Employee;
use App\Models\WorkOrderService;
use App\Models\WorkOrderServiceTechnician;

class WorkOrderTechnicianService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function assign(WorkOrderService $service, Employee $employee, array $data = []): WorkOrderServiceTechnician
    {
        if ($employee->company_id !== $service->workOrder->company_id || $employee->branch_id !== $service->workOrder->branch_id || $employee->status !== 'active') {
            throw new BusinessRuleException('Technician is outside the work-order branch.');
        }

        return WorkOrderServiceTechnician::query()->updateOrCreate(
            ['work_order_service_id' => $service->id, 'employee_id' => $employee->id],
            [
                'role' => $data['role'] ?? 'technician', 'is_primary' => $data['is_primary'] ?? false,
                'hourly_cost_snapshot' => $data['hourly_cost_snapshot'] ?? null, 'status' => 'assigned',
                'assigned_at' => now(), 'assigned_by' => $this->tenant->user()->id,
            ]
        );
    }
}
