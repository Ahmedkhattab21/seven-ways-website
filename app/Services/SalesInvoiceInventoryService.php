<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMovement;
use App\Models\Warehouse;

class SalesInvoiceInventoryService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function issue(SalesInvoice $invoice): void
    {
        if ($invoice->status !== 'approved') {
            throw new BusinessRuleException('Only approved invoices can issue inventory.');
        }

        foreach ($invoice->items()->where('item_type', 'product')->lockForUpdate()->get() as $item) {
            if ($item->issued_movement_id) {
                continue;
            }

            $product = Product::query()
                ->whereKey($item->product_id)
                ->where('company_id', $invoice->company_id)
                ->where('is_active', true)
                ->where('is_sellable', true)
                ->first();
            if (! $product || bccomp((string) $item->quantity, '0', 6) !== 1) {
                throw new BusinessRuleException('بيانات منتج الفاتورة غير صالحة للصرف من المخزون.');
            }

            $branchProductExists = BranchProduct::query()
                ->where('company_id', $invoice->company_id)
                ->where('branch_id', $invoice->branch_id)
                ->where('product_id', $product->id)
                ->where('is_available', true)
                ->where('is_sellable', true)
                ->exists();
            if (! $branchProductExists) {
                throw new BusinessRuleException("المنتج {$product->name} غير متاح للبيع في فرع الفاتورة.");
            }

            $warehouse = Warehouse::query()
                ->with('branch')
                ->whereKey($item->warehouse_id)
                ->where('company_id', $invoice->company_id)
                ->where('branch_id', $invoice->branch_id)
                ->where('is_active', true)
                ->where('is_system', false)
                ->where('allows_sale_issue', true)
                ->first();
            if (! $warehouse) {
                throw new BusinessRuleException('يجب تحديد مخزن بيع نشط تابع لفرع الفاتورة لكل منتج.');
            }

            $movement = $this->inventory->issue(
                $warehouse,
                $product,
                (string) $item->quantity,
                'sales_issue',
                ['type' => 'sales_invoice', 'id' => $invoice->id],
                fn (string $available): string => sprintf(
                    'لا يمكن إصدار الفاتورة. المنتج %s غير متوفر بالمخزون الكافي في %s - %s. المتاح: %s، المطلوب: %s.',
                    $product->name,
                    $warehouse->name,
                    $warehouse->branch?->name,
                    $this->formatQuantity($available),
                    $this->formatQuantity((string) $item->quantity)
                )
            );
            $item->forceFill([
                'issued_movement_id' => $movement->id,
                'cost_snapshot' => $movement->total_cost,
                'margin_snapshot' => bcsub((string) $item->total, (string) $movement->total_cost, 4),
            ])->save();
        }
    }

    public function return(SalesInvoiceItem $item, string $quantity, Warehouse $warehouse, array $reference = []): StockMovement
    {
        if ($item->item_type !== 'product' || ! $item->issued_movement_id) {
            throw new BusinessRuleException('Only issued invoice products can be returned.');
        }
        $remaining = bcsub((string) $item->quantity, (string) $item->returned_quantity, 6);
        if (bccomp($quantity, '0', 6) !== 1 || bccomp($quantity, $remaining, 6) === 1) {
            throw new BusinessRuleException('Return quantity exceeds the sold quantity.');
        }
        $unitCost = bcdiv((string) $item->cost_snapshot, (string) $item->quantity, 4);
        $movement = $this->inventory->receive(
            $warehouse,
            $item->product,
            $quantity,
            $unitCost,
            'sales_return',
            $reference ?: ['type' => 'sales_invoice_item', 'id' => $item->id]
        );
        $item->forceFill(['returned_quantity' => bcadd((string) $item->returned_quantity, $quantity, 6)])->save();

        return $movement;
    }

    private function formatQuantity(string $quantity): string
    {
        $formatted = rtrim(rtrim(number_format((float) $quantity, 6, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
