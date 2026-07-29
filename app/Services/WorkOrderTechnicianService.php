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
        $service->loadMissing('workOrder');
        if ($employee->company_id !== $service->workOrder->company_id || $employee->branch_id !== $service->workOrder->branch_id || $employee->status !== 'active') {
            throw new BusinessRuleException('الفني غير نشط أو خارج شركة أو فرع أمر العمل.', status: 403);
        }

        $qualified = $employee->serviceSkills()
            ->where('service_id', $service->service_id)
            ->where('company_id', $service->workOrder->company_id)
            ->where('branch_id', $service->workOrder->branch_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('certification_expires_at')
                ->orWhereDate('certification_expires_at', '>=', today()))
            ->exists();
        if (! $qualified) {
            throw new BusinessRuleException('الفني غير مؤهل للخدمة المطلوبة أو انتهت صلاحية مهارته.', status: 403);
        }

        $values = [
            'role' => $data['role'] ?? 'technician',
            'is_primary' => $data['is_primary'] ?? false,
            'status' => 'assigned',
            'assigned_at' => now(),
            'assigned_by' => $this->tenant->user()->id,
        ];
        if ($this->tenant->user()->hasPermission('work_orders.view_cost')) {
            $values['hourly_cost_snapshot'] = $data['hourly_cost_snapshot'] ?? null;
        }

        return WorkOrderServiceTechnician::query()->updateOrCreate(
            ['work_order_service_id' => $service->id, 'employee_id' => $employee->id],
            $values
        );
    }
}
