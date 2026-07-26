<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\GoodsReceiptPosted;
use App\Models\GoodsReceipt;
use App\Models\InventoryBatch;
use App\Models\PurchaseOrder;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

class GoodsReceiptPostingService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryService $inventory,
        private PurchaseRollReceivingService $rolls,
        private AuditService $audit,
        private MoneyRoundingService $rounding
    ) {
    }

    public function post(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = GoodsReceipt::whereKey($receipt->id)->lockForUpdate()
                ->with(['items.product', 'warehouse', 'purchaseOrder'])->firstOrFail();
            abort_unless($receipt->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($receipt->branch), 403);
            if ($receipt->posted_at || ! in_array($receipt->status, ['accepted', 'partially_rejected'], true)) {
                throw new BusinessRuleException('Only accepted receipts can be posted.');
            }
            if ($receipt->warehouse->is_system || $receipt->warehouse->warehouse_type === 'transit'
                || $receipt->warehouse->branch_id !== $receipt->branch_id) {
                throw new BusinessRuleException('The receiving warehouse is not allowed.');
            }
            foreach ($receipt->items()->lockForUpdate()->with('product')->get() as $item) {
                if ($item->stock_movement_id || $item->createdRolls()->exists()) {
                    throw new BusinessRuleException('This receipt item was already posted.');
                }
                $poItem = $item->purchaseOrderItem()->lockForUpdate()->first();
                if ($poItem) {
                    $remaining = bcsub($poItem->ordered_quantity, $poItem->received_quantity, 6);
                    $tolerance = bcmul($poItem->ordered_quantity, bcdiv((string) config('purchasing.quantity_tolerance_percentage', 0), '100', 8), 6);
                    $maximum = bcadd($remaining, $tolerance, 6);
                    if (bccomp($item->accepted_quantity, $maximum, 6) === 1
                        && ! $this->tenant->user()->hasPermission('goods_receipts.override_tolerance')) {
                        throw new BusinessRuleException('Over receipt requires override permission.');
                    }
                }
                $stockUnits = bcmul(
                    bcadd($item->accepted_quantity, $item->free_quantity, 6),
                    $item->conversion_factor,
                    6
                );
                if (bccomp($stockUnits, '0', 6) === 1) {
                    if ($item->product->tracking_type === 'roll') {
                        $this->rolls->receive($item);
                    } else {
                        $unitCost = $this->rounding->round(bcdiv($item->total_cost, $stockUnits, 8), 4);
                        $movement = $this->inventory->receive(
                            $receipt->warehouse,
                            $item->product,
                            $stockUnits,
                            $unitCost,
                            'purchase_receipt',
                            ['type' => 'goods_receipt_item', 'id' => $item->id],
                            $item->total_cost
                        );
                        $item->forceFill(['stock_movement_id' => $movement->id])->save();
                        $this->batch($receipt, $item, $stockUnits, $unitCost, $item->total_cost);
                    }
                }
                if ($poItem) {
                    $poItem->forceFill([
                        'received_quantity' => bcadd($poItem->received_quantity, $item->accepted_quantity, 6),
                    ])->save();
                    SupplierProduct::where('supplier_id', $receipt->supplier_id)
                        ->where('product_id', $item->product_id)
                        ->update(['last_purchase_price' => $item->unit_cost]);
                }
            }
            if ($receipt->purchaseOrder) {
                $this->refreshOrder($receipt->purchaseOrder);
            }
            $receipt->forceFill([
                'status' => 'posted', 'posted_by' => $this->tenant->user()->id, 'posted_at' => now(),
            ])->save();
            $this->audit->record('goods_receipt.posted', $receipt);
            DB::afterCommit(fn () => event(new GoodsReceiptPosted($receipt->id)));

            return $receipt;
        });
    }

    private function batch(
        GoodsReceipt $receipt,
        $item,
        string $quantity,
        string $unitCost,
        string $totalCost
    ): void {
        $poItem = $item->purchaseOrderItem;
        if (($poItem?->batch_required || $item->batch_number) && blank($item->batch_number)) {
            throw new BusinessRuleException('Batch number is required.');
        }
        if (($poItem?->expiry_required) && ! $item->expiry_date) {
            throw new BusinessRuleException('Expiry date is required.');
        }
        if (! $item->batch_number) {
            return;
        }
        $expired = $item->expiry_date?->isPast();
        if ($expired) {
            throw new BusinessRuleException('Expired batches cannot enter available stock.');
        }
        $batch = InventoryBatch::query()
            ->where('company_id', $receipt->company_id)
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $receipt->warehouse_id)
            ->where('batch_number', $item->batch_number)
            ->lockForUpdate()
            ->first();
        if ($batch) {
            if (($batch->expiry_date?->toDateString()) !== ($item->expiry_date?->toDateString())) {
                throw new BusinessRuleException('The batch expiry date must match the existing batch.');
            }
            $received = bcadd($batch->received_quantity, $quantity, 6);
            $available = bcadd($batch->available_quantity, $quantity, 6);
            $value = bcadd($batch->total_cost, $totalCost, 4);
            $batch->forceFill([
                'received_quantity' => $received,
                'available_quantity' => $available,
                'total_cost' => $value,
                'unit_cost' => $this->rounding->round(bcdiv($value, $received, 8), 4),
                'status' => 'active',
            ])->save();

            return;
        }
        $batch = new InventoryBatch;
        $batch->forceFill([
            'company_id' => $receipt->company_id,
            'product_id' => $item->product_id,
            'warehouse_id' => $receipt->warehouse_id,
            'batch_number' => $item->batch_number,
            'manufacture_date' => $item->manufacture_date,
            'expiry_date' => $item->expiry_date,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'total_cost' => $totalCost,
            'unit_cost' => $unitCost,
            'supplier_id' => $receipt->supplier_id,
            'goods_receipt_item_id' => $item->id,
            'status' => 'active',
        ])->save();
    }

    private function refreshOrder(PurchaseOrder $order): void
    {
        $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
        $receivedValue = '0.0000';
        $all = true;
        foreach ($order->items as $item) {
            $receivedValue = bcadd($receivedValue, bcmul($item->received_quantity, $item->unit_price, 4), 4);
            $all = $all && bccomp($item->received_quantity, $item->ordered_quantity, 6) >= 0;
        }
        $order->forceFill([
            'received_amount' => $receivedValue,
            'status' => $all ? 'fully_received' : 'partially_received',
        ])->save();
    }
}
