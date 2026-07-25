<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Service;
use App\Models\StockBalance;
use App\Models\VehicleSize;
use App\Models\VehicleType;

class ServiceMaterialEstimator
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function estimate(
        Service $service,
        ?VehicleSize $vehicleSize = null,
        ?VehicleType $vehicleType = null,
        string|int $quantity = 1
    ): array {
        if ($service->company_id !== $this->tenant->companyId() || bccomp((string) $quantity, '0', 4) !== 1) {
            throw new BusinessRuleException('Invalid service scope or quantity.', status: 403);
        }
        $requirements = $service->materialRequirements()->with(['product', 'unit'])
            ->where(fn ($q) => $vehicleSize
                ? $q->whereNull('vehicle_size_id')->orWhere('vehicle_size_id', $vehicleSize->id)
                : $q->whereNull('vehicle_size_id'))
            ->where(fn ($q) => $vehicleType
                ? $q->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleType->id)
                : $q->whereNull('vehicle_type_id'))
            ->get()->sortByDesc(fn ($item) => ($item->vehicle_size_id ? 2 : 0) + ($item->vehicle_type_id ? 1 : 0))
            ->unique('product_id');
        $warnings = [];
        $materials = $requirements->map(function ($item) use ($quantity, &$warnings) {
            $base = bcmul($item->expected_quantity, (string) $quantity, 6);
            $waste = bcdiv(bcmul($base, $item->expected_waste_percentage, 10), '100', 6);
            $total = bcadd($base, $waste, 6);
            $unitCost = $this->estimatedUnitCost($item->product_id);
            if ($unitCost === null) {
                $warnings[] = "No cost is available for {$item->product->name}.";
            }

            return [
                'requirement_id' => $item->id, 'product_id' => $item->product_id,
                'product' => $item->product->name, 'product_name' => $item->product->name,
                'unit_id' => $item->unit_id, 'unit' => $item->unit->symbol, 'expected_quantity' => $base,
                'expected_waste' => $waste, 'total_expected_quantity' => $total,
                'estimated_unit_cost' => $unitCost,
                'estimated_cost' => $unitCost === null ? null : bcmul($total, $unitCost, 4),
                'estimated_waste_cost' => $unitCost === null ? null : bcmul($waste, $unitCost, 4),
            ];
        })->values();
        $profiles = $service->rollProfiles()->with('filmProduct')
            ->where(fn ($q) => $vehicleSize
                ? $q->whereNull('vehicle_size_id')->orWhere('vehicle_size_id', $vehicleSize->id)
                : $q->whereNull('vehicle_size_id'))
            ->where(fn ($q) => $vehicleType
                ? $q->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleType->id)
                : $q->whereNull('vehicle_type_id'))->get();
        $rolls = $profiles->map(function ($profile) use ($quantity, &$warnings) {
            $area = bcmul($profile->expected_area, (string) $quantity, 6);
            $waste = bcdiv(bcmul($area, $profile->expected_waste_percentage, 10), '100', 6);
            $cost = $profile->film_product_id ? $this->estimatedUnitCost($profile->film_product_id) : null;
            if ($cost === null) {
                $warnings[] = 'No estimated film cost is available for a roll profile.';
            }

            return [
                'coverage_type' => $profile->coverage_type, 'film_product_id' => $profile->film_product_id,
                'expected_area' => $area, 'expected_waste_area' => $waste,
                'total_expected_area' => bcadd($area, $waste, 6),
                'estimated_cost' => $cost === null ? null : bcmul(bcadd($area, $waste, 6), $cost, 4),
                'estimated_waste_cost' => $cost === null ? null : bcmul($waste, $cost, 4),
            ];
        });
        $knownCosts = $materials->pluck('estimated_cost')->merge($rolls->pluck('estimated_cost'))->filter(fn ($v) => $v !== null);
        $totalCost = $knownCosts->reduce(fn ($carry, $cost) => bcadd($carry, $cost, 4), '0.0000');

        return [
            'materials' => $materials, 'roll_profiles' => $rolls, 'expected_roll_area' => $rolls->pluck('expected_area')
                ->reduce(fn ($carry, $area) => bcadd($carry, $area, 6), '0.000000'),
            'estimated_material_cost' => $knownCosts->isEmpty() ? null : $totalCost,
            'warnings' => array_values(array_unique($warnings)),
            'is_estimate' => true, 'stock_effect' => false,
        ];
    }

    private function estimatedUnitCost(int $productId): ?string
    {
        $balances = StockBalance::query()->where('company_id', $this->tenant->companyId())
            ->where('product_id', $productId)->where('quantity', '>', 0)->get();
        if ($balances->isEmpty()) {
            return null;
        }
        $quantity = $balances->reduce(fn ($carry, $balance) => bcadd($carry, $balance->quantity, 6), '0');
        $value = $balances->reduce(
            fn ($carry, $balance) => bcadd($carry, bcmul($balance->quantity, $balance->average_cost, 4), 4),
            '0'
        );

        return bccomp($quantity, '0', 6) === 1 ? bcdiv($value, $quantity, 4) : null;
    }
}
