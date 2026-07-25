<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Employee;
use App\Models\WorkOrderService;
use App\Models\WorkOrderServiceTimeLog;
use Illuminate\Support\Facades\DB;

class WorkOrderTimeTrackingService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function open(WorkOrderService $service, Employee $employee, string $action = 'work'): WorkOrderServiceTimeLog
    {
        return DB::transaction(function () use ($service, $employee, $action) {
            if (! $service->technicians()->where('employee_id', $employee->id)->whereNot('status', 'removed')->exists()) {
                throw new BusinessRuleException('Technician is not assigned to this service.');
            }
            if ($service->timeLogs()->where('employee_id', $employee->id)->whereNull('ended_at')->lockForUpdate()->exists()) {
                throw new BusinessRuleException('Technician already has an open time log for this service.');
            }

            return $service->timeLogs()->create([
                'employee_id' => $employee->id, 'action' => $action, 'started_at' => now(),
                'created_by' => $this->tenant->user()->id,
            ]);
        });
    }

    public function close(WorkOrderService $service, Employee $employee, ?string $reason = null): WorkOrderServiceTimeLog
    {
        return DB::transaction(function () use ($service, $employee, $reason) {
            $log = $service->timeLogs()->where('employee_id', $employee->id)->whereNull('ended_at')->lockForUpdate()->first();
            if (! $log) {
                throw new BusinessRuleException('No open time log was found.');
            }
            $endedAt = now();
            $log->forceFill(['ended_at' => $endedAt, 'duration_minutes' => $log->started_at->diffInMinutes($endedAt), 'reason' => $reason])->save();

            return $log;
        });
    }

    public function closeAll(WorkOrderService $service, ?string $reason = null): void
    {
        $service->timeLogs()->whereNull('ended_at')->get()->each(function (WorkOrderServiceTimeLog $log) use ($reason) {
            $endedAt = now();
            $log->forceFill(['ended_at' => $endedAt, 'duration_minutes' => $log->started_at->diffInMinutes($endedAt), 'reason' => $reason])->save();
        });
    }
}
