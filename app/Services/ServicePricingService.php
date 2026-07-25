<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Unit;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServicePricingService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(Service $service, Branch $branch, array $data, ?ServicePrice $price = null): ServicePrice
    {
        $this->assertScope($service, $branch);
        $price ??= new ServicePrice;
        if ($price->exists && $price->company_id !== $service->company_id) {
            throw new BusinessRuleException('Price is outside the current company.', status: 403);
        }
        $this->validatePriceScope($service, $data);
        $from = Carbon::parse($data['effective_from']);
        $to = ! empty($data['effective_to']) ? Carbon::parse($data['effective_to']) : null;
        $overlap = ServicePrice::query()
            ->where('branch_id', $branch->id)->where('service_id', $service->id)
            ->where('vehicle_size_id', $data['vehicle_size_id'] ?? null)
            ->where('vehicle_type_id', $data['vehicle_type_id'] ?? null)
            ->where('unit_id', $data['unit_id'] ?? null)
            ->where('priority', $data['priority'] ?? 0)->where('is_active', true)
            ->when($price->exists, fn ($q) => $q->where('id', '!=', $price->id))
            ->whereDate('effective_from', '<=', ($to ?: Carbon::parse('9999-12-31'))->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString()))
            ->exists();
        if ($overlap) {
            throw new BusinessRuleException('An overlapping active price with the same scope and priority already exists.');
        }

        return DB::transaction(function () use ($service, $branch, $data, $price) {
            $price->fill($data)->forceFill([
                'company_id' => $service->company_id, 'branch_id' => $branch->id, 'service_id' => $service->id,
            ])->save();
            $this->audit->record('service_price.saved', $price, ['service_id' => $service->id, 'branch_id' => $branch->id]);

            return $price;
        });
    }

    public function resolvePrice(
        Service $service,
        Branch $branch,
        ?VehicleSize $vehicleSize = null,
        ?VehicleType $vehicleType = null,
        string|int $quantity = 1,
        CarbonInterface|string|null $date = null
    ): array {
        $this->assertScope($service, $branch);
        if (bccomp((string) $quantity, '0', 4) !== 1) {
            throw new BusinessRuleException('Quantity must be greater than zero.');
        }
        foreach ([$vehicleSize, $vehicleType] as $vehicleReference) {
            if ($vehicleReference && $vehicleReference->company_id && $vehicleReference->company_id !== $service->company_id) {
                throw new BusinessRuleException('Vehicle reference is outside the current company.', status: 403);
            }
        }
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?: now());
        $availability = BranchService::query()->where('branch_id', $branch->id)->where('service_id', $service->id)
            ->where('is_active', true)->where('is_available', true)->first();
        if (! $availability) {
            throw new BusinessRuleException('Service is not available at this branch.');
        }
        $price = ServicePrice::query()->where('company_id', $service->company_id)
            ->where('branch_id', $branch->id)->where('service_id', $service->id)
            ->where('is_active', true)->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->where(fn ($q) => $vehicleSize
                ? $q->whereNull('vehicle_size_id')->orWhere('vehicle_size_id', $vehicleSize->id)
                : $q->whereNull('vehicle_size_id'))
            ->where(fn ($q) => $vehicleType
                ? $q->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleType->id)
                : $q->whereNull('vehicle_type_id'))
            ->orderByRaw('CASE WHEN vehicle_size_id IS NULL THEN 0 ELSE 2 END + CASE WHEN vehicle_type_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')->orderByDesc('effective_from')->first();
        $unitPrice = $price?->price ?? $availability->default_price;
        if ($unitPrice === null) {
            return [
                'unit_price' => null, 'subtotal' => null, 'total_price' => null, 'price_source' => 'custom_quote',
                'minimum_price' => $availability->minimum_price, 'estimated_duration' => $availability->default_duration_minutes
                    ?? $service->default_duration_minutes, 'tax' => ['rate' => null, 'amount' => null],
                'tax_rate' => null, 'tax_amount' => null, 'total' => null,
            ];
        }
        $subtotal = bcmul((string) $unitPrice, (string) $quantity, 4);
        $taxRate = $service->defaultTax?->is_active ? (string) $service->defaultTax->rate : '0';
        $taxAmount = bcdiv(bcmul($subtotal, $taxRate, 8), '100', 4);
        $total = bcadd($subtotal, $taxAmount, 4);

        return [
            'unit_price' => bcadd((string) $unitPrice, '0', 4), 'subtotal' => $subtotal,
            'total_price' => $total, 'price_source' => $price ? 'service_price' : 'branch_default',
            'minimum_price' => $price?->minimum_price ?? $availability->minimum_price,
            'estimated_duration' => $price?->estimated_duration_minutes
                ?? $availability->default_duration_minutes ?? $service->default_duration_minutes,
            'tax' => ['rate' => $taxRate, 'amount' => $taxAmount],
            'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'total' => $total,
        ];
    }

    private function assertScope(Service $service, Branch $branch): void
    {
        if ($service->company_id !== $this->tenant->companyId() || $branch->company_id !== $service->company_id
            || ! $this->tenant->user()?->canAccessBranch($branch)) {
            throw new BusinessRuleException('Service or branch is outside your tenant scope.', status: 403);
        }
    }

    private function validatePriceScope(Service $service, array $data): void
    {
        foreach ([
            'vehicle_size_id' => VehicleSize::class,
            'vehicle_type_id' => VehicleType::class,
        ] as $key => $model) {
            if (! empty($data[$key])) {
                $model::query()->whereKey($data[$key])
                    ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $service->company_id))
                    ->where('is_active', true)->firstOrFail();
            }
        }
        if (! empty($data['unit_id'])) {
            Unit::query()->whereKey($data['unit_id'])
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $service->company_id))
                ->where('is_active', true)->firstOrFail();
        }
        if ($service->pricing_type === 'by_vehicle_size' && empty($data['vehicle_size_id'])) {
            throw new BusinessRuleException('Vehicle-size pricing requires a vehicle size.');
        }
        if ($service->pricing_type === 'by_vehicle_type' && empty($data['vehicle_type_id'])) {
            throw new BusinessRuleException('Vehicle-type pricing requires a vehicle type.');
        }
        if ($service->pricing_type === 'per_unit' && empty($data['unit_id'])) {
            throw new BusinessRuleException('Per-unit pricing requires a unit.');
        }
    }
}
