<?php

namespace App\Services\CentralApproval\Handlers;

use App\Models\PurchaseRequisition;
use App\Services\CentralApproval\Contracts\ApprovalHandler;
use App\Services\PurchaseRequisitionApprovalService;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionApprovalHandler implements ApprovalHandler
{
    public function __construct(private PurchaseRequisitionApprovalService $service)
    {
    }

    public function modelClass(): string
    {
        return PurchaseRequisition::class;
    }

    public function module(): string
    {
        return 'purchasing';
    }

    public function pendingStatus(): string
    {
        return 'pending_approval';
    }

    public function permission(): string
    {
        return 'purchase_requisitions.approve';
    }

    public function documentNumber(Model $document): ?string
    {
        return $document->requisition_number;
    }

    public function amount(Model $document): ?string
    {
        return (string) $document->estimated_total;
    }

    public function currencyId(Model $document): ?int
    {
        return $document->currency_id;
    }

    public function branchId(Model $document): ?int
    {
        return $document->branch_id;
    }

    public function requesterId(Model $document): int
    {
        return $document->created_by;
    }

    public function approve(Model $document): void
    {
        $this->service->approve($document);
    }

    public function supportsReject(): bool
    {
        return true;
    }

    public function reject(Model $document, string $reason): void
    {
        $this->service->reject($document, $reason);
    }

    public function route(Model $document): ?string
    {
        return route('purchase-requisitions.show', $document);
    }
}
