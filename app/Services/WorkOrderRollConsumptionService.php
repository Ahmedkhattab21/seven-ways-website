<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderRollConsumed;
use App\Models\InventoryRoll;
use App\Models\WorkOrderMaterial;
use App\Models\WorkOrderWasteRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderRollConsumptionService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private RollConsumptionService $rolls,
        private WorkOrderCostService $costs
    ) {
    }

    public function consume(WorkOrderMaterial $line, string $length, string $usableArea, string $wasteArea = '0'): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line, $length, $usableArea, $wasteArea) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($line->material_type !== 'roll' || ! $line->roll_id || $line->status !== 'reserved') {
                throw new BusinessRuleException('A reserved roll material is required.');
            }
            $this->reservations->consume($line->reservation);
            $roll = InventoryRoll::findOrFail($line->roll_id);
            $this->rolls->consume($roll, $length, $usableArea, $wasteArea, [
                'type' => $line->rework_order_id ? 'rework_order' : 'work_order',
                'id' => $line->rework_order_id ?: $line->work_order_id,
            ]);
            $unitCost = $roll->unit_cost_per_area;
            $line->forceFill([
                'issued_quantity' => bcadd($usableArea, $wasteArea, 6), 'used_quantity' => $usableArea,
                'waste_quantity' => $wasteArea, 'unit_cost' => $unitCost,
                'used_cost' => bcmul($usableArea, $unitCost, 4), 'waste_cost' => bcmul($wasteArea, $unitCost, 4),
                'status' => 'consumed', 'used_by' => $this->tenant->user()->id,
            ])->save();
            if (bccomp($wasteArea, '0', 6) === 1) {
                WorkOrderWasteRecord::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'work_order_id' => $line->work_order_id,
                    'work_order_service_id' => $line->work_order_service_id,
                    'work_order_material_id' => $line->id,
                    'product_id' => $line->product_id,
                    'roll_id' => $line->roll_id,
                    'area' => $wasteArea,
                    'unit_cost' => $unitCost,
                    'total_cost' => bcmul($wasteArea, $unitCost, 4),
                    'reason_code' => 'normal_cutting',
                    'created_by' => $this->tenant->user()->id,
                ]);
            }
            $this->costs->rebuild($line->workOrder);
            DB::afterCommit(fn () => event(new WorkOrderRollConsumed($line->work_order_id, $line->id)));

            return $line;
        });
    }
}
