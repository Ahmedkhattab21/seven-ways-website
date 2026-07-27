<?php

namespace App\Listeners;

use App\Events\PurchaseOrderSubmitted;
use App\Events\PurchaseRequisitionSubmitted;
use App\Events\TreasuryTransferSubmitted;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\TreasuryTransfer;
use App\Services\CentralApprovalService;

class CreateCentralApprovalTask
{
    public function __construct(private CentralApprovalService $approvals)
    {
    }

    public function handle(object $event): void
    {
        $model = match ($event::class) {
            PurchaseOrderSubmitted::class => PurchaseOrder::findOrFail($event->purchaseOrderId),
            PurchaseRequisitionSubmitted::class => PurchaseRequisition::findOrFail($event->requisitionId),
            TreasuryTransferSubmitted::class => TreasuryTransfer::findOrFail($event->modelId),
        };
        $this->approvals->request($model);
    }
}
