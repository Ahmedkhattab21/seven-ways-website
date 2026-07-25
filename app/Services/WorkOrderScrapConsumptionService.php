<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\WorkOrderRollConsumed;
use App\Models\RollScrap;
use App\Models\WorkOrderMaterial;
use Illuminate\Support\Facades\DB;

class WorkOrderScrapConsumptionService
{
    public function __construct(
        private TenantContext $tenant,
        private InventoryReservationService $reservations,
        private RollScrapService $scraps,
        private WorkOrderCostService $costs
    ) {
    }

    public function consume(WorkOrderMaterial $line): WorkOrderMaterial
    {
        return DB::transaction(function () use ($line) {
            $line = WorkOrderMaterial::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($line->material_type !== 'scrap' || ! $line->scrap_id || $line->status !== 'reserved') {
                throw new BusinessRuleException('A reserved scrap material is required.');
            }
            $this->reservations->consume($line->reservation);
            $scrap = RollScrap::findOrFail($line->scrap_id);
            $this->scraps->consume($scrap, $line->rework_order_id ? [
                'type' => 'rework_order', 'id' => $line->rework_order_id,
            ] : []);
            $line->forceFill([
                'issued_quantity' => $scrap->area, 'used_quantity' => $scrap->area,
                'unit_cost' => $scrap->unit_cost_per_area, 'used_cost' => $scrap->total_cost,
                'status' => 'consumed', 'used_by' => $this->tenant->user()->id,
            ])->save();
            $this->costs->rebuild($line->workOrder);
            DB::afterCommit(fn () => event(new WorkOrderRollConsumed($line->work_order_id, $line->id)));

            return $line;
        });
    }
}
