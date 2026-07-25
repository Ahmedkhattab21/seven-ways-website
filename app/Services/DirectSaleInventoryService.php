<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMovement;
use App\Models\Warehouse;

class DirectSaleInventoryService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function issue(SalesInvoice $invoice): void
    {
        if ($invoice->invoice_type !== 'direct_sale') {
            return;
        }
        foreach ($invoice->items()->where('item_type', 'product')->lockForUpdate()->get() as $item) {
            if ($item->issued_movement_id) {
                continue;
            }
            if (! $item->product_id || ! $item->warehouse_id) {
                throw new BusinessRuleException('Direct-sale products require a warehouse.');
            }
            $movement = $this->inventory->issue(
                Warehouse::findOrFail($item->warehouse_id),
                Product::findOrFail($item->product_id),
                $item->quantity,
                'sales_issue',
                ['type' => 'sales_invoice', 'id' => $invoice->id]
            );
            $item->forceFill([
                'issued_movement_id' => $movement->id,
                'cost_snapshot' => $movement->total_cost,
                'margin_snapshot' => bcsub($item->total, $movement->total_cost, 4),
            ])->save();
        }
    }

    public function return(SalesInvoiceItem $item, string $quantity, Warehouse $warehouse): StockMovement
    {
        if ($item->invoice->invoice_type !== 'direct_sale' || $item->item_type !== 'product' || ! $item->issued_movement_id) {
            throw new BusinessRuleException('Only issued direct-sale products can be returned.');
        }
        $remaining = bcsub($item->quantity, $item->returned_quantity, 6);
        if (bccomp($quantity, '0', 6) !== 1 || bccomp($quantity, $remaining, 6) === 1) {
            throw new BusinessRuleException('Return quantity exceeds the sold quantity.');
        }
        $unitCost = bcdiv((string) $item->cost_snapshot, (string) $item->quantity, 4);
        $movement = $this->inventory->receive($warehouse, $item->product, $quantity, $unitCost, 'sales_return', [
            'type' => 'sales_invoice_item', 'id' => $item->id,
        ]);
        $item->forceFill(['returned_quantity' => bcadd($item->returned_quantity, $quantity, 6)])->save();

        return $movement;
    }
}
