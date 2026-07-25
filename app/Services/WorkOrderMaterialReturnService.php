<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderMaterialReturned;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WorkOrderMaterial;
use Illuminate\Support\Facades\DB;

class WorkOrderMaterialReturnService
{
    public function __construct(private TenantContext $tenant, private InventoryService $inventory)
    {
    }

    public function return(WorkOrderMaterial $line, string $quantity): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line, $quantity) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $remaining = bcsub($line->issued_quantity, bcadd($line->used_quantity, bcadd($line->waste_quantity, $line->returned_quantity, 6), 6), 6);
            if ($line->material_type !== 'quantity' || bccomp($quantity, '0', 6) <= 0 || bccomp($quantity, $remaining, 6) === 1) {
                throw new BusinessRuleException('Return exceeds unused issued quantity.');
            }
            $this->inventory->receive(
                Warehouse::findOrFail($line->warehouse_id), Product::findOrFail($line->product_id),
                $quantity, $line->unit_cost, 'work_order_return', ['type' => 'work_order', 'id' => $line->work_order_id]
            );
            $returned = bcadd($line->returned_quantity, $quantity, 6);
            $settled = bcadd($line->used_quantity, bcadd($line->waste_quantity, $returned, 6), 6);
            $line->forceFill(['returned_quantity' => $returned, 'status' => bccomp($settled, $line->issued_quantity, 6) === 0 ? 'returned' : 'partially_used', 'returned_by' => $this->tenant->user()->id])->save();
            DB::afterCommit(fn () => event(new WorkOrderMaterialReturned($line->work_order_id, $line->id)));

            return $line;
        });
    }
}
