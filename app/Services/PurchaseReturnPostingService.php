<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PurchaseReturnPosted;
use App\Models\InventoryBatch;
use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\DB;

class PurchaseReturnPostingService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryService $inventory,
        private RollService $rolls,
        private AuditService $audit
    ) {
    }

    public function post(PurchaseReturn $return): PurchaseReturn
    {
        return DB::transaction(function () use ($return) {
            $return = PurchaseReturn::whereKey($return->id)->lockForUpdate()
                ->with(['items.receiptItem', 'items.roll', 'warehouse'])->firstOrFail();
            abort_unless($return->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($return->branch), 403);
            if ($return->status !== 'approved') {
                throw new BusinessRuleException('Only approved purchase returns can be posted.');
            }
            foreach ($return->items()->lockForUpdate()->get() as $item) {
                if ($item->stock_movement_id) {
                    throw new BusinessRuleException('Purchase return item was already posted.');
                }
                if ($item->receiptItem) {
                    $prior = (string) \App\Models\PurchaseReturnItem::query()
                        ->where('goods_receipt_item_id', $item->goods_receipt_item_id)
                        ->where('id', '!=', $item->id)
                        ->whereHas('purchaseReturn', fn ($query) => $query->where('status', 'posted'))
                        ->sum('quantity');
                    $available = bcsub($item->receiptItem->accepted_quantity, $prior, 6);
                    if (bccomp($item->quantity, $available, 6) === 1) {
                        throw new BusinessRuleException('Return exceeds the accepted receipt quantity.');
                    }
                }
                if ($item->roll) {
                    $roll = \App\Models\InventoryRoll::whereKey($item->roll_id)->lockForUpdate()->firstOrFail();
                    if ($roll->status !== 'available'
                        || bccomp($roll->remaining_length, $roll->original_length, 6) !== 0
                        || bccomp($item->quantity, '1', 6) !== 0) {
                        throw new BusinessRuleException('Only a complete unused roll can be returned.');
                    }
                    $movement = $this->inventory->issueAtCost(
                        $return->warehouse,
                        $roll->product,
                        '1',
                        $roll->total_cost,
                        'purchase_return',
                        ['type' => 'purchase_return_item', 'id' => $item->id]
                    );
                    $this->rolls->recordMovement(
                        $roll,
                        'supplier_return',
                        $roll->remaining_length,
                        '0',
                        $roll->remaining_area,
                        '0',
                        ['type' => 'purchase_return_item', 'id' => $item->id, 'reason' => $return->reason]
                    );
                    $roll->forceFill(['status' => 'returned'])->save();
                } else {
                    $movement = $this->inventory->issueAtCost(
                        $return->warehouse,
                        $item->receiptItem?->product ?? \App\Models\Product::findOrFail($item->product_id),
                        $item->quantity,
                        $item->unit_cost,
                        'purchase_return',
                        ['type' => 'purchase_return_item', 'id' => $item->id]
                    );
                }
                $item->forceFill(['stock_movement_id' => $movement->id])->save();
                if ($item->batch_id) {
                    $batch = InventoryBatch::whereKey($item->batch_id)->lockForUpdate()->firstOrFail();
                    if (bccomp($item->quantity, $batch->available_quantity, 6) === 1) {
                        throw new BusinessRuleException('Return exceeds available batch quantity.');
                    }
                    $remaining = bcsub($batch->available_quantity, $item->quantity, 6);
                    $batch->forceFill([
                        'available_quantity' => $remaining,
                        'status' => bccomp($remaining, '0', 6) === 0 ? 'depleted' : $batch->status,
                    ])->save();
                }
                if ($item->receiptItem?->purchaseOrderItem) {
                    $poItem = $item->receiptItem->purchaseOrderItem()->lockForUpdate()->first();
                    $poItem->forceFill([
                        'returned_quantity' => bcadd($poItem->returned_quantity, $item->quantity, 6),
                    ])->save();
                }
            }
            $return->forceFill([
                'status' => 'posted', 'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            $this->audit->record('purchase_return.posted', $return);
            DB::afterCommit(fn () => event(new PurchaseReturnPosted($return->id)));

            return $return;
        });
    }
}
