<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\PurchaseOrderCreated;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private PurchaseOrderPricingService $pricing,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $supplier = Supplier::whereKey($data['supplier_id'])->where('company_id', $this->tenant->companyId())
                ->where('status', 'active')->firstOrFail();
            $pricing = $this->pricing->calculate($data, $items);
            if (bccomp((string) ($data['exchange_rate'] ?? 1), '0', 8) !== 1) {
                throw new BusinessRuleException('Exchange rate must be positive.');
            }
            $address = $supplier->addresses()->where('address_type', 'shipping')->where('is_primary', true)->first()
                ?? $supplier->addresses()->where('is_primary', true)->first();
            $order = new PurchaseOrder($data);
            $order->forceFill(array_merge($pricing['totals'], [
                'company_id' => $this->tenant->companyId(),
                'branch_id' => $this->tenant->branchId(),
                'purchase_order_number' => $this->numbers->next(
                    'purchase_order',
                    $this->tenant->companyId(),
                    $this->tenant->branchId(),
                    $data['order_date']
                ),
                'currency_id' => $data['currency_id'] ?? $supplier->currency_id ?? $this->tenant->company()->currency_id,
                'payment_terms_days' => $data['payment_terms_days'] ?? $supplier->payment_terms_days,
                'supplier_name_snapshot' => $supplier->name,
                'supplier_tax_number_snapshot' => $supplier->tax_number,
                'supplier_address_snapshot' => $address?->address_line,
                'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ]))->save();
            foreach ($pricing['items'] as $index => $input) {
                $product = Product::whereKey($input['product_id'])->where('company_id', $order->company_id)
                    ->where('is_purchasable', true)->where('is_active', true)->firstOrFail();
                $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)->where('is_active', true)->first();
                $requisitionItem = null;
                if (! empty($input['purchase_requisition_item_id'])) {
                    $requisitionItem = \App\Models\PurchaseRequisitionItem::query()
                        ->whereKey($input['purchase_requisition_item_id'])->lockForUpdate()
                        ->whereHas('requisition', fn ($query) => $query
                            ->where('company_id', $order->company_id)
                            ->where('branch_id', $order->branch_id)
                            ->where('status', 'approved'))
                        ->firstOrFail();
                    $remaining = bcsub($requisitionItem->approved_quantity, $requisitionItem->ordered_quantity, 6);
                    if (bccomp((string) $input['ordered_quantity'], $remaining, 6) === 1) {
                        throw new BusinessRuleException('Order quantity exceeds the approved requisition remainder.');
                    }
                }
                $orderItem = $order->items()->make();
                $orderItem->forceFill(array_merge($input, [
                    'description' => $input['description'] ?? $product->name,
                    'purchase_unit_id' => $input['purchase_unit_id'] ?? $supplierProduct?->purchase_unit_id ?? $product->purchase_unit_id,
                    'stock_unit_id' => $product->stock_unit_id,
                    'conversion_factor' => $input['conversion_factor'] ?? $supplierProduct?->conversion_factor ?? 1,
                    'tax_id' => $input['tax_id'] ?? $product->default_tax_id,
                    'sort_order' => $index,
                    'received_quantity' => 0, 'returned_quantity' => 0, 'invoiced_quantity' => 0,
                ]))->save();
                if ($requisitionItem) {
                    $ordered = bcadd($requisitionItem->ordered_quantity, (string) $input['ordered_quantity'], 6);
                    $requisitionItem->forceFill([
                        'ordered_quantity' => $ordered,
                        'status' => bccomp($ordered, $requisitionItem->approved_quantity, 6) >= 0 ? 'ordered' : 'partially_ordered',
                    ])->save();
                    $this->refreshRequisition($requisitionItem->requisition);
                }
            }
            $this->audit->record('purchase_order.created', $order);
            DB::afterCommit(fn () => event(new PurchaseOrderCreated($order->id)));

            return $order->load('items');
        });
    }

    public function fromRequisition(PurchaseRequisition $requisition, array $data, array $items): PurchaseOrder
    {
        abort_unless($requisition->company_id === $this->tenant->companyId()
            && $this->tenant->user()->canAccessBranch($requisition->branch), 403);
        if ($requisition->status !== 'approved') {
            throw new BusinessRuleException('Only approved requisitions can create purchase orders.');
        }

        return $this->create($data, $items);
    }

    private function refreshRequisition(PurchaseRequisition $requisition): void
    {
        $requisition->refresh();
        $pending = $requisition->items()->whereIn('status', ['approved', 'partially_ordered'])->exists();
        $ordered = $requisition->items()->where('ordered_quantity', '>', 0)->exists();
        $requisition->forceFill(['status' => $pending && $ordered ? 'partially_ordered' : ($pending ? 'approved' : 'ordered')])->save();
    }
}
