<?php

namespace App\Services\CentralApproval;

use App\Services\CentralApproval\Contracts\ApprovalHandler;
use App\Services\CentralApproval\Handlers\PurchaseOrderApprovalHandler;
use App\Services\CentralApproval\Handlers\PurchaseRequisitionApprovalHandler;
use App\Services\CentralApproval\Handlers\TreasuryTransferApprovalHandler;
use InvalidArgumentException;

class ApprovalModuleRegistry
{
    /** @var array<class-string, class-string<ApprovalHandler>> */
    private array $handlers = [
        \App\Models\PurchaseRequisition::class => PurchaseRequisitionApprovalHandler::class,
        \App\Models\PurchaseOrder::class => PurchaseOrderApprovalHandler::class,
        \App\Models\TreasuryTransfer::class => TreasuryTransferApprovalHandler::class,
    ];

    public function for(string $modelClass): ApprovalHandler
    {
        $handler = $this->handlers[$modelClass] ?? null;
        if (! $handler) {
            throw new InvalidArgumentException("No central approval handler registered for {$modelClass}.");
        }

        return app($handler);
    }

    public function registered(): array
    {
        return array_keys($this->handlers);
    }
}
