<?php

namespace App\Services;

use App\Models\WorkOrder;

class WorkOrderCostService
{
    public function rebuild(WorkOrder $order): WorkOrder
    {
        foreach ($order->services()->with(['materials', 'technicians'])->get() as $service) {
            $material = $service->materials->sum('used_cost');
            $waste = $order->wastes()->where('work_order_service_id', $service->id)->sum('total_cost');
            $labor = $service->technicians->sum('labor_cost');
            $service->forceFill([
                'actual_material_cost' => $material, 'actual_waste_cost' => $waste,
                'actual_labor_cost' => $labor, 'actual_total_cost' => $material + $waste + $labor,
            ])->save();
        }
        $material = $order->services()->sum('actual_material_cost');
        $waste = $order->wastes()->sum('total_cost');
        $labor = $order->services()->sum('actual_labor_cost');
        $total = $material + $waste + $labor;
        $order->forceFill([
            'actual_material_cost' => $material, 'actual_waste_cost' => $waste,
            'actual_labor_cost' => $labor, 'actual_total_cost' => $total,
            'actual_margin' => (float) $order->estimated_total - $total,
        ])->save();

        return $order->refresh();
    }
}
