<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderMaterialIssued;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WorkOrderMaterial;
use App\Models\WorkOrderWasteRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderMaterialIssueService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private InventoryService $inventory,
        private WorkOrderCostService $costs
    ) {
    }

    public function issue(WorkOrderMaterial $line, string $quantity): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line, $quantity) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $remainingReservation = bcsub($line->expected_quantity, $line->issued_quantity, 6);
            if ($line->material_type !== 'quantity' || $line->status !== 'reserved' || bccomp($quantity, '0', 6) <= 0
                || bccomp($quantity, $remainingReservation, 6) !== 0) {
                throw new BusinessRuleException('Issue the reserved quantity in full; split reservations are not supported.');
            }
            $warehouse = Warehouse::findOrFail($line->warehouse_id);
            if ($warehouse->is_system || ! $warehouse->allows_work_order_issue) {
                throw new BusinessRuleException('This warehouse cannot issue work-order materials.');
            }
            $this->reservations->consume($line->reservation);
            $movement = $this->inventory->issue($warehouse, Product::findOrFail($line->product_id), $quantity, 'work_order_issue', [
                'type' => $line->rework_order_id ? 'rework_order' : 'work_order',
                'id' => $line->rework_order_id ?: $line->work_order_id,
            ]);
            $issued = bcadd($line->issued_quantity, $quantity, 6);
            $line->forceFill([
                'issued_quantity' => $issued, 'unit_cost' => $movement->unit_cost,
                'issued_cost' => bcmul($issued, $movement->unit_cost, 4), 'status' => 'issued',
                'issued_by' => $this->tenant->user()->id,
            ])->save();
            DB::afterCommit(fn () => event(new WorkOrderMaterialIssued($line->work_order_id, $line->id)));

            return $line;
        });
    }

    public function consume(WorkOrderMaterial $line, string $quantity, string $wasteQuantity = '0'): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line, $quantity, $wasteQuantity) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $available = bcsub(
                $line->issued_quantity,
                bcadd($line->used_quantity, bcadd($line->returned_quantity, $line->waste_quantity, 6), 6),
                6
            );
            $total = bcadd($quantity, $wasteQuantity, 6);
            if ($line->material_type !== 'quantity' || ! in_array($line->status, ['issued', 'partially_used'], true)
                || bccomp($quantity, '0', 6) <= 0 || bccomp($total, $available, 6) === 1) {
                throw new BusinessRuleException('Material usage exceeds unused issued quantity.');
            }
            $used = bcadd($line->used_quantity, $quantity, 6);
            $waste = bcadd($line->waste_quantity, $wasteQuantity, 6);
            $settled = bcadd($used, bcadd($waste, $line->returned_quantity, 6), 6);
            $line->forceFill([
                'used_quantity' => $used, 'waste_quantity' => $waste,
                'used_cost' => bcmul($used, $line->unit_cost, 4),
                'waste_cost' => bcmul($waste, $line->unit_cost, 4),
                'status' => bccomp($settled, $line->issued_quantity, 6) === 0 ? 'consumed' : 'partially_used',
                'used_by' => $this->tenant->user()->id,
            ])->save();
            if (bccomp($wasteQuantity, '0', 6) === 1) {
                WorkOrderWasteRecord::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'work_order_id' => $line->work_order_id,
                    'work_order_service_id' => $line->work_order_service_id,
                    'work_order_material_id' => $line->id,
                    'product_id' => $line->product_id,
                    'quantity' => $wasteQuantity,
                    'unit_cost' => $line->unit_cost,
                    'total_cost' => bcmul($wasteQuantity, $line->unit_cost, 4),
                    'reason_code' => 'normal_cutting',
                    'created_by' => $this->tenant->user()->id,
                ]);
            }
            $this->costs->rebuild($line->workOrder);

            return $line;
        });
    }
}
