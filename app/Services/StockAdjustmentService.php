<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(private TenantContext $tenant, private InventoryService $inventory, private AuditService $audit)
    {
    }

    public function post(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment) {
            $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->with('items')->firstOrFail();
            if (! in_array($adjustment->status, ['draft', 'approved'], true)) {
                throw new BusinessRuleException('Only draft or approved adjustment can be posted.');
            }
            $warehouse = Warehouse::findOrFail($adjustment->warehouse_id);
            if ($warehouse->is_system || $warehouse->warehouse_type === 'transit') {
                throw new BusinessRuleException('Manual adjustments cannot use a system Transit warehouse.');
            }
            foreach ($adjustment->items->sortBy('product_id') as $item) {
                if ($item->inventory_roll_id || $item->roll_scrap_id) {
                    throw new BusinessRuleException('Tracked roll adjustments require the roll status workflow.');
                }
                $product = Product::findOrFail($item->product_id);
                $incoming = in_array($adjustment->adjustment_type, ['increase', 'correction'], true);
                $reference = ['type' => 'stock_adjustment', 'id' => $adjustment->id];
                $incoming
                    ? $this->inventory->receive($warehouse, $product, $item->quantity, $item->unit_cost ?? '0', 'adjustment_in', $reference)
                    : $this->inventory->issue($warehouse, $product, $item->quantity, 'adjustment_out', $reference);
            }
            $adjustment->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->tenant->user()->id])->save();
            $this->audit->record('stock_adjustment.posted', $adjustment);

            return $adjustment;
        });
    }
}
