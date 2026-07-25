<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderDelivered;
use App\Models\InventoryReservation;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderDeliveryService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private WarrantyIssuanceService $warranties,
        private AuditService $audit
    ) {
    }

    public function deliver(WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $data) {
            $workOrder = WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()
                ->with(['deliveryInspection.attachments', 'appointment'])->firstOrFail();
            abort_unless(
                (int) $workOrder->company_id === (int) $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($workOrder->branch),
                403
            );
            if ($workOrder->status !== 'ready_for_delivery') {
                throw new BusinessRuleException('The work order is not ready for delivery.');
            }
            if (! $workOrder->qualityChecks()->where('status', 'passed')->exists()) {
                throw new BusinessRuleException('Final quality approval is required.');
            }
            $inspection = $workOrder->deliveryInspection;
            if (! $inspection || ! in_array($inspection->status, ['completed', 'customer_acknowledged'], true)
                || ! $inspection->attachments->contains('category', 'delivery_overview')
                || ! $inspection->attachments->contains('category', 'delivery_signature')) {
                throw new BusinessRuleException('A completed delivery inspection, final photos, and private signature are required.');
            }
            if ($workOrder->materials()->whereIn('status', ['planned', 'issued', 'partially_used'])->exists()) {
                throw new BusinessRuleException('Open work-order materials must be settled before delivery.');
            }
            InventoryReservation::query()
                ->where('reference_type', 'work_order')
                ->where('reference_id', $workOrder->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->each(fn ($reservation) => $this->reservations->release($reservation));

            $signature = $inspection->attachments->firstWhere('category', 'delivery_signature');
            $from = $workOrder->status;
            $workOrder->forceFill([
                'status' => 'delivered', 'delivered_at' => now(), 'delivered_by' => $this->tenant->user()->id,
                'delivery_receiver_name' => $data['receiver_name'],
                'delivery_receiver_contact' => $data['receiver_contact'] ?? null,
                'delivery_signature_path' => $signature->path,
                'updated_by' => $this->tenant->user()->id,
            ])->save();
            $workOrder->statusLogs()->create([
                'from_status' => $from, 'to_status' => 'delivered',
                'reason' => 'Vehicle delivered to customer', 'changed_by' => $this->tenant->user()->id,
            ]);
            if ($workOrder->appointment) {
                $workOrder->appointment->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }
            $this->warranties->issueForWorkOrder($workOrder);
            $this->audit->record('work_order.delivered', $workOrder, ['receiver_name' => $data['receiver_name']]);
            DB::afterCommit(fn () => event(new WorkOrderDelivered($workOrder->id)));

            return $workOrder->refresh();
        });
    }
}
