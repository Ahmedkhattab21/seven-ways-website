<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderCancelled;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderCancellationService
{
    public function __construct(
        private TenantContext $tenant,
        private WorkOrderTimeTrackingService $time,
        private WorkOrderMaterialReservationService $reservations
    ) {
    }

    public function cancel(WorkOrder $order, string $reason): WorkOrder
    {
        if (in_array($order->status, ['awaiting_quality', 'ready_for_delivery', 'delivered', 'closed', 'cancelled'], true)) {
            throw new BusinessRuleException('This work order can no longer be cancelled.');
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->services()->get()->each(fn ($service) => $this->time->closeAll($service, 'work_order_cancelled'));
            $this->reservations->release($order);
            if ($order->materials()->whereColumn('issued_quantity', '>', DB::raw('used_quantity + returned_quantity + waste_quantity'))->exists()) {
                throw new BusinessRuleException('Return or settle unused issued materials before cancellation.');
            }
            $from = $order->status;
            $order->forceFill([
                'status' => 'cancelled', 'cancellation_reason' => $reason,
                'cancelled_by' => $this->tenant->user()->id, 'updated_by' => $this->tenant->user()->id,
            ])->save();
            $order->statusLogs()->create(['from_status' => $from, 'to_status' => 'cancelled', 'reason' => $reason, 'changed_by' => $this->tenant->user()->id]);
            DB::afterCommit(fn () => event(new WorkOrderCancelled($order->id)));

            return $order;
        });
    }
}
