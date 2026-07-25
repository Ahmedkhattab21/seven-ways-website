<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\GoodsReceiptCreated;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $items) {
            $warehouse = Warehouse::whereKey($data['warehouse_id'])
                ->where('company_id', $this->tenant->companyId())
                ->where('branch_id', $this->tenant->branchId())
                ->where('is_active', true)->where('is_system', false)
                ->where('warehouse_type', '!=', 'transit')->firstOrFail();
            $order = null;
            if (! empty($data['purchase_order_id'])) {
                $order = PurchaseOrder::whereKey($data['purchase_order_id'])
                    ->where('company_id', $this->tenant->companyId())
                    ->where('branch_id', $this->tenant->branchId())
                    ->whereIn('status', ['sent', 'partially_received'])->firstOrFail();
            }
            $supplierId = $order?->supplier_id ?? $data['supplier_id'];
            $receipt = new GoodsReceipt($data);
            $receipt->forceFill([
                'company_id' => $this->tenant->companyId(), 'branch_id' => $this->tenant->branchId(),
                'supplier_id' => $supplierId, 'warehouse_id' => $warehouse->id,
                'goods_receipt_number' => $this->numbers->next(
                    'goods_receipt',
                    $this->tenant->companyId(),
                    $this->tenant->branchId(),
                    $data['receipt_date']
                ),
                'status' => 'draft', 'received_by' => $this->tenant->user()->id,
            ])->save();
            foreach ($items as $input) {
                $poItem = null;
                if (! empty($input['purchase_order_item_id'])) {
                    $poItem = $order?->items()->whereKey($input['purchase_order_item_id'])->firstOrFail();
                }
                $productId = $poItem?->product_id ?? $input['product_id'];
                $product = Product::whereKey($productId)->where('company_id', $receipt->company_id)->firstOrFail();
                $received = (string) $input['received_quantity'];
                $accepted = (string) ($input['accepted_quantity'] ?? $received);
                $rejected = (string) ($input['rejected_quantity'] ?? 0);
                $free = (string) ($input['free_quantity'] ?? 0);
                if (bccomp($received, '0', 6) !== 1 || bccomp($accepted, '0', 6) < 0
                    || bccomp($rejected, '0', 6) < 0 || bccomp($free, '0', 6) < 0
                    || bccomp(bcadd($accepted, $rejected, 6), $received, 6) !== 0) {
                    throw new BusinessRuleException('Accepted and rejected quantities must equal the received quantity.');
                }
                $unitCost = (string) ($input['unit_cost'] ?? $poItem?->unit_price ?? 0);
                $totalCost = bcmul($accepted, $unitCost, 4);
                $receiptItem = $receipt->items()->make();
                $receiptItem->forceFill(array_merge($input, [
                    'product_id' => $product->id,
                    'unit_id' => $input['unit_id'] ?? $poItem?->purchase_unit_id ?? $product->purchase_unit_id,
                    'conversion_factor' => $input['conversion_factor'] ?? $poItem?->conversion_factor ?? 1,
                    'ordered_quantity_snapshot' => $poItem?->ordered_quantity,
                    'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected,
                    'free_quantity' => $free, 'unit_cost' => $unitCost, 'total_cost' => $totalCost,
                ]))->save();
            }
            $this->audit->record('goods_receipt.created', $receipt);
            DB::afterCommit(fn () => event(new GoodsReceiptCreated($receipt->id)));

            return $receipt->load('items');
        });
    }

    public function receive(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = $this->lockScoped($receipt);
            if ($receipt->status !== 'draft') {
                throw new BusinessRuleException('Only draft receipts can be marked received.');
            }
            $needsInspection = config('purchasing.goods_receipt_inspection_required', false);
            $hasRejected = $receipt->items()->where('rejected_quantity', '>', 0)->exists();
            $receipt->forceFill([
                'status' => $needsInspection
                    ? 'inspection_pending'
                    : ($hasRejected ? 'partially_rejected' : 'accepted'),
                'received_at' => now(),
            ])->save();

            return $receipt;
        });
    }

    private function lockScoped(GoodsReceipt $receipt): GoodsReceipt
    {
        $receipt = GoodsReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
        abort_unless($receipt->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($receipt->branch), 403);

        return $receipt;
    }
}
