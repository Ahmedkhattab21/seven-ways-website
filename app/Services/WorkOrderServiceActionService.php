<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderAwaitingQuality;
use App\Events\WorkOrderPaused;
use App\Events\WorkOrderResumed;
use App\Events\WorkOrderServiceCompleted;
use App\Events\WorkOrderStarted;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Models\WorkOrderService;
use Illuminate\Support\Facades\DB;

class WorkOrderServiceActionService
{
    public function __construct(
        private TenantContext $tenant,
        private WorkOrderTimeTrackingService $time,
        private WorkOrderCostService $costs
    ) {
    }

    public function start(WorkOrderService $service, Employee $employee, bool $materialsOverride = false): WorkOrderService
    {
        return DB::transaction(function () use ($service, $employee, $materialsOverride) {
            $service = WorkOrderService::query()->whereKey($service->id)->lockForUpdate()->with('workOrder.inspection')->firstOrFail();
            if (! in_array($service->workOrder->inspection?->status, ['completed', 'customer_acknowledged'], true)
                || ! $service->technicians()->where('employee_id', $employee->id)->exists()) {
                throw new BusinessRuleException('Completed inspection and assigned technician are required.');
            }
            if (! $materialsOverride && $service->materials()->whereIn('status', ['planned'])->exists()) {
                throw new BusinessRuleException('Materials must be reserved before work starts.');
            }
            if (! in_array($service->status, ['planned', 'ready', 'paused', 'rework_required'], true)) {
                throw new BusinessRuleException('Service cannot be started in its current state.');
            }
            $resuming = $service->status === 'paused';
            $this->time->open($service, $employee, $resuming ? 'resume' : 'work');
            $service->forceFill(['status' => 'in_progress', 'started_at' => $service->started_at ?? now(), 'paused_at' => null])->save();
            $this->transitionOrder($service->workOrder, 'in_progress');
            DB::afterCommit(fn () => event($resuming
                ? new WorkOrderResumed($service->work_order_id, $service->id)
                : new WorkOrderStarted($service->work_order_id, $service->id)));

            return $service;
        });
    }

    public function pause(WorkOrderService $service, Employee $employee, ?string $reason = null): WorkOrderService
    {
        if ($service->status !== 'in_progress') {
            throw new BusinessRuleException('Only an active service can be paused.');
        }

        return DB::transaction(function () use ($service, $employee, $reason) {
            $this->time->close($service, $employee, $reason);
            $service->forceFill(['status' => 'paused', 'paused_at' => now()])->save();
            if (! $service->workOrder->services()->where('status', 'in_progress')->exists()) {
                $this->transitionOrder($service->workOrder, 'paused', $reason);
            }
            DB::afterCommit(fn () => event(new WorkOrderPaused($service->work_order_id, $service->id)));

            return $service;
        });
    }

    public function resume(WorkOrderService $service, Employee $employee): WorkOrderService
    {
        return $this->start($service, $employee);
    }

    public function complete(WorkOrderService $service): WorkOrderService
    {
        return DB::transaction(function () use ($service) {
            $service = WorkOrderService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            if (! in_array($service->status, ['in_progress', 'paused'], true)) {
                throw new BusinessRuleException('Only started services can be completed.');
            }
            if ($service->materials()->where(function ($query) {
                $query->whereColumn('issued_quantity', '>', DB::raw('used_quantity + returned_quantity + waste_quantity'));
            })->exists()) {
                throw new BusinessRuleException('Issued materials must be used, returned, or recorded as waste.');
            }
            $this->time->closeAll($service, 'service_completed');
            $minutes = (int) $service->timeLogs()->sum('duration_minutes');
            $service->technicians()->each(function ($technician) use ($service) {
                $worked = (int) $service->timeLogs()->where('employee_id', $technician->employee_id)->sum('duration_minutes');
                $rate = $technician->hourly_cost_snapshot ?? 0;
                $technician->forceFill([
                    'worked_minutes' => $worked, 'labor_cost' => round($worked * (float) $rate / 60, 4),
                    'status' => 'completed', 'finished_at' => now(),
                ])->save();
            });
            $service->forceFill(['status' => 'completed', 'actual_duration_minutes' => $minutes, 'completed_at' => now()])->save();
            DB::afterCommit(fn () => event(new WorkOrderServiceCompleted($service->work_order_id, $service->id)));
            $order = $service->workOrder;
            $this->costs->rebuild($order);
            if (! $order->services()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                $from = $order->status;
                $order->forceFill([
                    'status' => 'awaiting_quality', 'finished_at' => now(), 'ready_for_quality_at' => now(),
                    'updated_by' => $this->tenant->user()->id,
                ])->save();
                $order->statusLogs()->create([
                    'from_status' => $from, 'to_status' => 'awaiting_quality',
                    'changed_by' => $this->tenant->user()->id,
                ]);
                DB::afterCommit(fn () => event(new WorkOrderAwaitingQuality($order->id)));
            }

            return $service;
        });
    }

    public function reopen(WorkOrderService $service, string $reason): WorkOrderService
    {
        return DB::transaction(function () use ($service, $reason) {
            $service = WorkOrderService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($service->status !== 'completed' || ! $this->tenant->user()->hasPermission('work_orders.reopen')) {
                throw new BusinessRuleException('Service cannot be reopened.', status: 403);
            }
            $order = WorkOrder::query()->whereKey($service->work_order_id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['ready_for_delivery', 'delivered', 'cancelled', 'closed'], true)) {
                throw new BusinessRuleException('Services cannot be reopened in this work-order state.');
            }
            $service->forceFill(['status' => 'rework_required', 'completed_at' => null, 'notes' => trim($service->notes."\n".$reason)])->save();
            $this->transitionOrder($order, 'in_progress', $reason);

            return $service;
        });
    }

    private function transitionOrder(WorkOrder $order, string $status, ?string $reason = null): void
    {
        if ($order->status === $status) {
            return;
        }
        $from = $order->status;
        $order->forceFill([
            'status' => $status,
            'started_at' => $status === 'in_progress' ? ($order->started_at ?? now()) : $order->started_at,
            'finished_at' => $status === 'in_progress' ? null : $order->finished_at,
            'ready_for_quality_at' => $status === 'in_progress' ? null : $order->ready_for_quality_at,
            'updated_by' => $this->tenant->user()->id,
        ])->save();
        $order->statusLogs()->create(['from_status' => $from, 'to_status' => $status, 'reason' => $reason, 'changed_by' => $this->tenant->user()->id]);
    }
}
