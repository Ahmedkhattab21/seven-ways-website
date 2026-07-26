<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Events\SupplierInvoiceCreated;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private SupplierInvoicePricingService $pricing,
        private SupplierInvoiceMatchingService $matching,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): SupplierInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $supplier = Supplier::whereKey($data['supplier_id'])->where('company_id', $this->tenant->companyId())->firstOrFail();
            $order = ! empty($data['purchase_order_id'])
                ? PurchaseOrder::whereKey($data['purchase_order_id'])->where('company_id', $supplier->company_id)
                    ->where('branch_id', $this->tenant->branchId())->where('supplier_id', $supplier->id)->firstOrFail()
                : null;
            $receipt = ! empty($data['goods_receipt_id'])
                ? GoodsReceipt::whereKey($data['goods_receipt_id'])->where('company_id', $supplier->company_id)
                    ->where('branch_id', $this->tenant->branchId())->where('supplier_id', $supplier->id)
                    ->where('status', 'posted')->firstOrFail()
                : null;
            $pricing = $this->pricing->calculate($data, $items);
            $address = $supplier->addresses()->where('address_type', 'billing')->where('is_primary', true)->first()
                ?? $supplier->addresses()->where('is_primary', true)->first();
            $invoice = new SupplierInvoice($data);
            $invoice->forceFill(array_merge($pricing['totals'], [
                'company_id' => $supplier->company_id, 'branch_id' => $this->tenant->branchId(),
                'internal_invoice_number' => $this->numbers->next(
                    'supplier_invoice',
                    $supplier->company_id,
                    $this->tenant->branchId(),
                    $data['invoice_date']
                ),
                'currency_id' => $data['currency_id'] ?? $order?->currency_id ?? $supplier->currency_id
                    ?? $this->tenant->company()->currency_id,
                'supplier_name_snapshot' => $supplier->name,
                'supplier_tax_number_snapshot' => $supplier->tax_number,
                'supplier_address_snapshot' => $address?->address_line,
                'status' => 'draft', 'created_by' => $this->tenant->user()->id,
            ]))->save();
            foreach ($pricing['items'] as $item) {
                if (! empty($item['product_id'])) {
                    Product::whereKey($item['product_id'])->where('company_id', $invoice->company_id)->firstOrFail();
                }
                if (! empty($item['purchase_order_item_id'])) {
                    $order?->items()->whereKey($item['purchase_order_item_id'])->firstOrFail();
                }
                if (! empty($item['goods_receipt_item_id'])) {
                    $receipt?->items()->whereKey($item['goods_receipt_item_id'])->firstOrFail();
                }
                $invoiceItem = $invoice->items()->make();
                $invoiceItem->forceFill($item + ['matched_quantity' => 0])->save();
            }
            if ($order || $receipt || config('purchasing.supplier_invoice_matching_required', true)) {
                $this->matching->match($invoice);
            }
            $this->audit->record('supplier_invoice.created', $invoice);
            DB::afterCommit(fn () => event(new SupplierInvoiceCreated($invoice->id)));

            return $invoice->load('items.matches');
        });
    }
}
