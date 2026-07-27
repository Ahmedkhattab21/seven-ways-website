<?php

namespace App\Services\CentralApproval\Handlers;

use App\Models\PurchaseOrder;
use App\Services\CentralApproval\Contracts\ApprovalHandler;
use App\Services\PurchaseOrderApprovalService;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderApprovalHandler implements ApprovalHandler
{
    public function __construct(private PurchaseOrderApprovalService $service)
    {
    }

    public function modelClass(): string
    {
        return PurchaseOrder::class;
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
        return 'purchase_orders.approve';
    }

    public function documentNumber(Model $document): ?string
    {
        return $document->purchase_order_number;
    }

    public function amount(Model $document): ?string
    {
        return (string) $document->total;
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
        return false;
    }

    public function reject(Model $document, string $reason): void
    {
        throw new \LogicException('Purchase orders do not support rejection.');
    }

    public function route(Model $document): ?string
    {
        return route('purchase-orders.show', $document);
    }
}
