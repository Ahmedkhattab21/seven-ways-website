<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryReservation;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

class StockTransferPreparationService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function prepare(StockTransfer $transfer, array $quantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $quantities) {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['approved', 'preparing'], true)
                || ! $this->tenant->accessibleBranches()->contains('id', $transfer->from_branch_id)) {
                throw new BusinessRuleException('Transfer cannot be prepared from this branch.', status: 403);
            }
            $items = $transfer->items()->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $prepared = (string) ($quantities[$item->id] ?? $item->approved_quantity);
                if (bccomp($prepared, '0', 6) <= 0 || bccomp($prepared, $item->approved_quantity, 6) === 1) {
                    throw new BusinessRuleException('Prepared quantity exceeds approved quantity.');
                }
                if (! InventoryReservation::query()->where('reference_type', 'stock_transfer')
                    ->where('reference_id', $transfer->id)->where('product_id', $item->product_id)
                    ->where('status', 'active')->exists()) {
                    throw new BusinessRuleException('Transfer reservation is missing.');
                }
                $item->forceFill(['prepared_quantity' => $prepared])->save();
            }
            $ready = $items->every(
                fn ($item) => bccomp($item->prepared_quantity, $item->approved_quantity, 6) === 0
            );
            $transfer->forceFill([
                'status' => $ready ? 'ready_to_ship' : 'preparing',
                'prepared_by' => $this->tenant->user()->id, 'prepared_at' => now(),
            ])->save();
            $this->audit->record($ready ? 'stock_transfer.prepared' : 'stock_transfer.preparing', $transfer);

            return $transfer;
        });
    }
}
