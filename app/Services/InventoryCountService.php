<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryCountService
{
    public function __construct(private TenantContext $tenant, private InventoryService $inventory, private AuditService $audit)
    {
    }

    public function snapshot(InventoryCount $count): InventoryCount
    {
        return DB::transaction(function () use ($count) {
            $count = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            if ($count->status !== 'draft' || $count->items()->exists()) {
                throw new BusinessRuleException('Inventory count was already snapshotted.');
            }
            $warehouse = Warehouse::findOrFail($count->warehouse_id);
            if ($warehouse->is_system || $warehouse->warehouse_type === 'transit') {
                throw new BusinessRuleException('Manual inventory counts cannot use a system Transit warehouse.');
            }
            StockBalance::query()->where('warehouse_id', $count->warehouse_id)->orderBy('product_id')->each(
                fn (StockBalance $balance) => InventoryCountItem::create([
                    'inventory_count_id' => $count->id, 'product_id' => $balance->product_id,
                    'system_quantity' => $balance->quantity, 'unit_cost' => $balance->average_cost,
                ])
            );
            $count->forceFill(['status' => 'counting', 'snapshot_at' => now()])->save();

            return $count;
        });
    }

    public function post(InventoryCount $count): InventoryCount
    {
        return DB::transaction(function () use ($count) {
            $count = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($count->status !== 'counting') {
                throw new BusinessRuleException('Only a counting document can be posted.');
            }
            $warehouse = Warehouse::findOrFail($count->warehouse_id);
            if ($warehouse->is_system || $warehouse->warehouse_type === 'transit') {
                throw new BusinessRuleException('Manual inventory counts cannot use a system Transit warehouse.');
            }
            foreach ($count->items->sortBy('product_id') as $item) {
                if ($item->counted_quantity === null) {
                    continue;
                }
                $difference = bcsub($item->counted_quantity, $item->system_quantity, 6);
                $item->forceFill(['difference_quantity' => $difference])->save();
                if (bccomp($difference, '0', 6) === 0) {
                    continue;
                }
                $product = Product::findOrFail($item->product_id);
                $reference = ['type' => 'inventory_count', 'id' => $count->id];
                bccomp($difference, '0', 6) === 1
                    ? $this->inventory->receive($warehouse, $product, $difference, $item->unit_cost, 'inventory_count_gain', $reference)
                    : $this->inventory->issue($warehouse, $product, bcsub('0', $difference, 6), 'inventory_count_loss', $reference);
            }
            $count->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->tenant->user()->id])->save();
            $this->audit->record('inventory_count.posted', $count);

            return $count;
        });
    }
}
