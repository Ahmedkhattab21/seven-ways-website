<?php

namespace App\Services\CentralApproval\Handlers;

use App\Core\Tenancy\TenantContext;
use App\Models\TreasuryTransfer;
use App\Services\CentralApproval\Contracts\ApprovalHandler;
use App\Services\TreasuryApprovalLimitService;
use App\Services\TreasuryTransferService;
use Illuminate\Database\Eloquent\Model;

class TreasuryTransferApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private TreasuryTransferService $service,
        private TreasuryApprovalLimitService $limits,
        private TenantContext $tenant
    ) {
    }

    public function modelClass(): string
    {
        return TreasuryTransfer::class;
    }

    public function module(): string
    {
        return 'treasury';
    }

    public function pendingStatus(): string
    {
        return 'pending_approval';
    }

    public function permission(): string
    {
        return 'treasury.transfers.approve';
    }

    public function documentNumber(Model $document): ?string
    {
        return $document->document_number;
    }

    public function amount(Model $document): ?string
    {
        return (string) $document->amount;
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
        $this->limits->assert($this->tenant->user(), 'transfer', 'approve', $document->currency_id, (string) $document->amount, $document->branch_id);
        $this->service->action($document, 'approve');
    }

    public function supportsReject(): bool
    {
        return false;
    }

    public function reject(Model $document, string $reason): void
    {
        throw new \LogicException('Treasury transfers do not support rejection.');
    }

    public function route(Model $document): ?string
    {
        return route('treasury.transfers.index').'#'.$document->uuid;
    }
}
