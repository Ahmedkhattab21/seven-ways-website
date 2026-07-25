<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryReservation;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

class StockTransferCancellationService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private AuditService $audit
    ) {
    }

    public function cancel(StockTransfer $transfer, string $reason): StockTransfer
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Cancellation reason is required.');
        }

        return DB::transaction(function () use ($transfer, $reason) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['draft', 'pending_approval', 'approved', 'preparing', 'ready_to_ship'], true)
                || $transfer->company_id !== $this->tenant->companyId()) {
                throw new BusinessRuleException('A shipped transfer cannot be cancelled.');
            }
            InventoryReservation::query()->where('reference_type', 'stock_transfer')
                ->where('reference_id', $transfer->id)->where('status', 'active')
                ->orderBy('id')->lockForUpdate()->get()
                ->each(fn (InventoryReservation $reservation) => $this->reservations->release($reservation));
            $transfer->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()->id,
                'cancelled_at' => now(), 'cancellation_reason' => $reason,
            ])->save();
            $this->audit->record('stock_transfer.cancelled', $transfer);

            return $transfer;
        });
    }
}
