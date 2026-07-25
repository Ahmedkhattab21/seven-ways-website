<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Service;
use App\Models\ServiceMaterialRequirement;
use App\Models\ServiceMaterialSubstitute;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;

class ServiceMaterialRequirementService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(Service $service, array $data, ?ServiceMaterialRequirement $requirement = null): ServiceMaterialRequirement
    {
        if ($service->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Service is outside the current company.', status: 403);
        }
        if ($requirement?->exists && $requirement->company_id !== $service->company_id) {
            throw new BusinessRuleException('Material requirement is outside the current company.', status: 403);
        }
        foreach (['vehicle_size_id' => VehicleSize::class, 'vehicle_type_id' => VehicleType::class] as $key => $model) {
            if (! empty($data[$key])) {
                $model::query()->whereKey($data[$key])
                    ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $service->company_id))
                    ->where('is_active', true)->firstOrFail();
            }
        }
        $product = Product::query()->whereKey($data['product_id'])->where('company_id', $service->company_id)
            ->where('is_active', true)->firstOrFail();
        if (! $product->is_consumable && ($data['requirement_type'] ?? null) !== 'tool') {
            throw new BusinessRuleException('The selected product is not consumable.');
        }
        $validUnits = [$product->purchase_unit_id, $product->stock_unit_id, $product->sale_unit_id];
        if (! in_array((int) $data['unit_id'], $validUnits, true)
            && ! ProductUnitConversion::query()->where('product_id', $product->id)
                ->where(fn ($q) => $q->where('from_unit_id', $data['unit_id'])->orWhere('to_unit_id', $data['unit_id']))->exists()) {
            throw new BusinessRuleException('No compatible product unit conversion exists.');
        }

        return DB::transaction(function () use ($service, $data, $requirement) {
            $requirement ??= new ServiceMaterialRequirement;
            $requirement->fill($data)->forceFill(['company_id' => $service->company_id, 'service_id' => $service->id])->save();
            $this->audit->record('service_material.saved', $requirement, ['service_id' => $service->id]);

            return $requirement;
        });
    }

    public function saveSubstitute(
        ServiceMaterialRequirement $requirement,
        Product $substitute,
        string $conversionFactor,
        int $priority = 0
    ): ServiceMaterialSubstitute {
        $requirement->loadMissing(['service', 'product']);
        if ($requirement->company_id !== $this->tenant->companyId()
            || $substitute->company_id !== $requirement->company_id
            || ! $substitute->is_active
            || $substitute->is($requirement->product)
            || bccomp($conversionFactor, '0', 6) !== 1) {
            throw new BusinessRuleException('Invalid material substitute or conversion factor.');
        }
        $compatible = in_array($requirement->unit_id, [
            $substitute->purchase_unit_id, $substitute->stock_unit_id, $substitute->sale_unit_id,
        ], true) || ProductUnitConversion::query()->where('product_id', $substitute->id)
            ->where(fn ($q) => $q->where('from_unit_id', $requirement->unit_id)
                ->orWhere('to_unit_id', $requirement->unit_id))->exists();
        if (! $compatible) {
            throw new BusinessRuleException('Substitute product unit is incompatible.');
        }
        $record = ServiceMaterialSubstitute::query()->updateOrCreate(
            ['service_material_requirement_id' => $requirement->id, 'substitute_product_id' => $substitute->id],
            ['conversion_factor' => $conversionFactor, 'priority' => $priority, 'is_active' => true]
        );
        $this->audit->record('service_material_substitute.saved', $record, ['requirement_id' => $requirement->id]);

        return $record;
    }
}
