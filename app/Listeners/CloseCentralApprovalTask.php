<?php

namespace App\Listeners;

use App\Events\PurchaseOrderApproved;
use App\Events\PurchaseRequisitionApproved;
use App\Events\PurchaseRequisitionRejected;
use App\Events\TreasuryTransferApproved;
use App\Models\ApprovalTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\TreasuryTransfer;

class CloseCentralApprovalTask
{
    public function handle(object $event): void
    {
        [$type, $id, $status] = match ($event::class) {
            PurchaseOrderApproved::class => [PurchaseOrder::class, $event->purchaseOrderId, 'approved'],
            PurchaseRequisitionApproved::class => [PurchaseRequisition::class, $event->requisitionId, 'approved'],
            PurchaseRequisitionRejected::class => [PurchaseRequisition::class, $event->requisitionId, 'rejected'],
            TreasuryTransferApproved::class => [TreasuryTransfer::class, $event->modelId, 'approved'],
        };

        ApprovalTask::where('approvable_type', $type)->where('approvable_id', $id)
            ->where('status', 'pending')->update([
                'status' => $status, 'decision' => $status, 'completed_at' => now(), 'updated_at' => now(),
            ]);
    }
}
