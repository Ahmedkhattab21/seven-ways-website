<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryRoll;
use App\Models\PurchaseReturn;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $items) {
            $warehouse = Warehouse::whereKey($data['warehouse_id'])->where('company_id', $this->tenant->companyId())
                ->where('branch_id', $this->tenant->branchId())->where('is_active', true)->where('is_system', false)->firstOrFail();
            $return = new PurchaseReturn($data);
            $return->forceFill([
                'company_id' => $this->tenant->companyId(), 'branch_id' => $this->tenant->branchId(),
                'purchase_return_number' => $this->numbers->next(
                    'purchase_return',
                    $this->tenant->companyId(),
                    $this->tenant->branchId(),
                    $data['return_date']
                ),
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ])->save();
            $subtotal = $tax = $total = '0.0000';
            foreach ($items as $input) {
                $receiptItem = null;
                if (! empty($input['goods_receipt_item_id'])) {
                    $receiptItem = GoodsReceiptItem::whereKey($input['goods_receipt_item_id'])
                        ->whereHas('receipt', fn ($query) => $query
                            ->where('company_id', $return->company_id)
                            ->where('branch_id', $return->branch_id)
                            ->where('warehouse_id', $warehouse->id)
                            ->where('supplier_id', $return->supplier_id)
                            ->where('status', 'posted'))
                        ->firstOrFail();
                }
                if (! empty($input['roll_id'])) {
                    InventoryRoll::whereKey($input['roll_id'])->where('company_id', $return->company_id)
                        ->where('branch_id', $return->branch_id)->where('warehouse_id', $warehouse->id)->firstOrFail();
                }
                $quantity = (string) $input['quantity'];
                $unitCost = (string) ($input['unit_cost'] ?? $receiptItem?->unit_cost ?? 0);
                if (bccomp($quantity, '0', 6) !== 1) {
                    throw new BusinessRuleException('Return quantity must be positive.');
                }
                $line = bcmul($quantity, $unitCost, 4);
                $lineTax = bcdiv(bcmul($line, (string) ($input['tax_rate'] ?? 0), 8), '100', 4);
                $returnItem = $return->items()->make();
                $returnItem->forceFill(array_merge($input, [
                    'product_id' => $receiptItem?->product_id ?? $input['product_id'],
                    'unit_id' => $input['unit_id'] ?? $receiptItem?->unit_id,
                    'unit_cost' => $unitCost, 'tax_amount' => $lineTax, 'total' => bcadd($line, $lineTax, 4),
                ]))->save();
                $subtotal = bcadd($subtotal, $line, 4);
                $tax = bcadd($tax, $lineTax, 4);
                $total = bcadd($total, bcadd($line, $lineTax, 4), 4);
            }
            $return->forceFill(['subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total])->save();
            $this->audit->record('purchase_return.created', $return);

            return $return->load('items');
        });
    }

    public function submit(PurchaseReturn $return): PurchaseReturn
    {
        return $this->transition($return, 'draft', 'pending_approval', 'created');
    }

    public function approve(PurchaseReturn $return): PurchaseReturn
    {
        if (config('purchasing.separation_of_duties', true) && $return->created_by === $this->tenant->user()->id) {
            throw new BusinessRuleException('The purchase return creator cannot approve it.');
        }

        return $this->transition($return, 'pending_approval', 'approved', 'approved');
    }

    private function transition(PurchaseReturn $return, string $from, string $to, string $action): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $from, $to, $action) {
            $return = PurchaseReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            abort_unless($return->company_id === $this->tenant->companyId()
                && $this->tenant->user()->canAccessBranch($return->branch), 403);
            if ($return->status !== $from) {
                throw new BusinessRuleException("Purchase return must be {$from}.");
            }
            $fields = ['status' => $to];
            if ($action === 'approved') {
                $fields += ['approved_by' => $this->tenant->user()->id, 'approved_at' => now()];
            }
            $return->forceFill($fields)->save();

            return $return;
        });
    }
}
