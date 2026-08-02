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
                throw new BusinessRuleException('لا يمكن بدء جرد غير موجود في حالة المسودة.');
            }
            $warehouse = Warehouse::findOrFail($count->warehouse_id);
            if ($count->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()->canAccessBranch($count->branch)
                || $warehouse->company_id !== $count->company_id
                || $warehouse->branch_id !== $count->branch_id) {
                throw new BusinessRuleException('لا تملك صلاحية بدء هذا الجرد.', status: 403);
            }
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

    public function record(InventoryCount $count, array $items): InventoryCount
    {
        return DB::transaction(function () use ($count, $items) {
            $count = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            if ($count->company_id !== $this->tenant->companyId()
                || ! $this->tenant->user()->canAccessBranch($count->branch)) {
                throw new BusinessRuleException('لا تملك صلاحية إدخال كميات هذا الجرد.', status: 403);
            }
            if ($count->status !== 'counting' || $count->counted_at !== null) {
                throw new BusinessRuleException('لا يمكن إدخال كميات لهذا الجرد في حالته الحالية.');
            }

            $countItems = $count->items()->lockForUpdate()->get()->keyBy('id');
            if ($countItems->isEmpty()
                || collect($items)->keys()->map(fn ($id) => (int) $id)->diff($countItems->keys())->isNotEmpty()) {
                throw new BusinessRuleException('تحتوي بيانات الجرد على بنود غير صالحة.');
            }

            foreach ($countItems as $item) {
                $submitted = $items[$item->id] ?? null;
                if (! is_array($submitted) || ! array_key_exists('counted_quantity', $submitted)) {
                    throw new BusinessRuleException('يجب إدخال الكمية المعدودة لكل منتج.');
                }
                $item->forceFill(['counted_quantity' => $submitted['counted_quantity']])->save();
            }

            $count->forceFill([
                'counted_by' => $this->tenant->user()->id,
                'counted_at' => now(),
            ])->save();

            return $count;
        });
    }

    public function post(InventoryCount $count): InventoryCount
    {
        return DB::transaction(function () use ($count) {
            $count = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($count->status !== 'counting' || $count->counted_at === null) {
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
