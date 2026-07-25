<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Models\VehicleSize;
use App\Models\VehicleType;

class ServiceCostEstimator
{
    public function __construct(
        private ServiceMaterialEstimator $materials,
        private ServicePricingService $pricing
    ) {
    }

    public function estimate(
        Service $service,
        Branch $branch,
        ?VehicleSize $vehicleSize = null,
        ?VehicleType $vehicleType = null,
        string|int $quantity = 1
    ): array {
        $material = $this->materials->estimate($service, $vehicleSize, $vehicleType, $quantity);
        $price = $this->pricing->resolvePrice($service, $branch, $vehicleSize, $vehicleType, $quantity);
        $materialCost = $material['estimated_material_cost'];
        $wasteCostItems = $material['materials']->pluck('estimated_waste_cost')
            ->merge($material['roll_profiles']->pluck('estimated_waste_cost'))->filter(fn ($value) => $value !== null);
        $wasteCost = $wasteCostItems->reduce(fn ($carry, $cost) => bcadd($carry, $cost, 4), '0.0000');
        $margin = $materialCost !== null && $price['subtotal'] !== null
            ? bcsub($price['subtotal'], $materialCost, 4) : null;

        return $material + $price + [
            'estimated_waste_cost' => $wasteCostItems->isEmpty() ? null : $wasteCost,
            'estimated_total_cost' => $materialCost,
            'estimated_margin' => $margin,
        ];
    }
}
