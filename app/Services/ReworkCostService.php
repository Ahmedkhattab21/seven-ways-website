<?php

namespace App\Services;

use App\Models\ReworkOrder;
use App\Models\WorkOrderWasteRecord;

class ReworkCostService
{
    public function rebuild(ReworkOrder $rework): ReworkOrder
    {
        $materialIds = $rework->materials()->pluck('id');
        $material = (float) $rework->materials()->sum('used_cost');
        $waste = (float) WorkOrderWasteRecord::query()
            ->whereIn('work_order_material_id', $materialIds)
            ->sum('total_cost');
        $labor = 0.0;
        if ($rework->started_at) {
            foreach ($rework->services()->with('workOrderService.technicians')->get() as $line) {
                foreach ($line->workOrderService->technicians as $technician) {
                    $minutes = $line->workOrderService->timeLogs()
                        ->where('employee_id', $technician->employee_id)
                        ->where('started_at', '>=', $rework->started_at)
                        ->sum('duration_minutes');
                    $labor += ((float) $technician->hourly_cost_snapshot * (int) $minutes) / 60;
                }
            }
        }
        $rework->forceFill([
            'additional_material_cost' => $material,
            'additional_waste_cost' => $waste,
            'additional_labor_cost' => round($labor, 4),
            'total_rework_cost' => round($material + $waste + $labor, 4),
        ])->save();

        return $rework->refresh();
    }
}
