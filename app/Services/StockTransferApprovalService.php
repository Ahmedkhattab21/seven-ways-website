<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryReservation;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockTransferApprovalService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private AuditService $audit
    ) {
    }

    public function approve(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'pending_approval' || $transfer->company_id !== $this->tenant->companyId()) {
                throw new BusinessRuleException('Transfer cannot be approved in its current state.');
            }
            $items = $transfer->items()->with(['product', 'roll', 'scrap.sourceRoll'])->orderBy('id')->lockForUpdate()->get();
            $source = Warehouse::findOrFail($transfer->from_warehouse_id);
            foreach ($items as $item) {
                $quantity = $item->item_type === 'scrap' ? '0' : ($item->item_type === 'roll' ? '1' : $item->requested_quantity);
                $this->reservations->reserve(
                    $source, $item->product, $quantity, 'stock_transfer', $transfer->id,
                    null, $item->roll, $item->scrap
                );
                $item->forceFill(['approved_quantity' => $item->requested_quantity])->save();
            }
            $transit = Warehouse::query()->where('company_id', $transfer->company_id)
                ->where('branch_id', $transfer->from_branch_id)->where('warehouse_type', 'transit')
                ->where('is_system', true)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $transfer->forceFill([
                'status' => 'approved', 'approved_by' => $this->tenant->user()->id,
                'approved_at' => now(), 'transit_warehouse_id' => $transit->id,
            ])->save();
            $this->audit->record('stock_transfer.approved', $transfer);

            return $transfer;
        });
    }

    public function reject(StockTransfer $transfer, string $reason): StockTransfer
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($transfer, $reason) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['pending_approval', 'approved'], true)) {
                throw new BusinessRuleException('Transfer cannot be rejected in its current state.');
            }
            $this->releaseActive($transfer);
            $transfer->forceFill([
                'status' => 'rejected', 'rejected_by' => $this->tenant->user()->id,
                'rejected_at' => now(), 'rejection_reason' => $reason,
            ])->save();
            $this->audit->record('stock_transfer.rejected', $transfer);

            return $transfer;
        });
    }

    private function releaseActive(StockTransfer $transfer): void
    {
        InventoryReservation::query()->where('reference_type', 'stock_transfer')->where('reference_id', $transfer->id)
            ->where('status', 'active')->orderBy('id')->lockForUpdate()->get()
            ->each(fn (InventoryReservation $reservation) => $this->reservations->release($reservation));
    }
}
