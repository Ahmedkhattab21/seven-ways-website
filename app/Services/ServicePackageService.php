<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchServicePackage;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\VehicleSize;
use Illuminate\Support\Facades\DB;

class ServicePackageService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function save(
        array $data,
        array $items,
        ?ServicePackage $package = null,
        ?array $branchPrice = null
    ): ServicePackage {
        if (empty($items)) {
            throw new BusinessRuleException('A package must contain at least one service.');
        }
        $ids = collect($items)->pluck('service_id');
        if ($ids->duplicates()->isNotEmpty()) {
            throw new BusinessRuleException('A service cannot be repeated inside a package.');
        }
        if (Service::query()->whereIn('id', $ids)->where('company_id', $this->tenant->companyId())->count() !== $ids->count()) {
            throw new BusinessRuleException('Every package service must belong to the current company.', status: 403);
        }

        return DB::transaction(function () use ($data, $items, $package, $branchPrice) {
            $package ??= new ServicePackage;
            if ($package->exists && $package->company_id !== $this->tenant->companyId()) {
                throw new BusinessRuleException('Package is outside the current company.', status: 403);
            }
            if (empty($data['code'])) {
                $data['code'] = $this->numbers->next('service_package', $this->tenant->companyId(), null);
            }
            $package->fill($data)->forceFill([
                'company_id' => $this->tenant->companyId(),
                $package->exists ? 'updated_by' : 'created_by' => $this->tenant->user()?->id,
            ])->save();
            $package->items()->delete();
            $package->items()->createMany($items);
            if ($branchPrice !== null) {
                $this->savePrice($package, $branchPrice);
            }
            $this->audit->record($package->wasRecentlyCreated ? 'service_package.created' : 'service_package.updated', $package);

            return $package;
        });
    }

    public function savePrice(ServicePackage $package, array $data): BranchServicePackage
    {
        if ($package->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Package is outside the current company.', status: 403);
        }
        $branch = Branch::query()->whereKey($data['branch_id'])
            ->where('company_id', $this->tenant->companyId())->firstOrFail();
        if (! $branch->is_active || ! $this->tenant->user()?->canAccessBranch($branch)) {
            throw new BusinessRuleException('Package price is outside your branch scope.', status: 403);
        }
        if (! empty($data['vehicle_size_id'])) {
            VehicleSize::query()->whereKey($data['vehicle_size_id'])
                ->where(fn ($query) => $query->whereNull('company_id')
                    ->orWhere('company_id', $this->tenant->companyId()))
                ->where('is_active', true)->firstOrFail();
        }
        $overlap = BranchServicePackage::query()
            ->where('branch_id', $branch->id)->where('service_package_id', $package->id)
            ->where('vehicle_size_id', $data['vehicle_size_id'] ?? null)->where('is_available', true)
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $data['effective_from']))
            ->exists();
        if ($overlap) {
            throw new BusinessRuleException('An overlapping available package price already exists.');
        }

        $price = new BranchServicePackage(collect($data)->except('branch_id')->all());
        $price->forceFill(['branch_id' => $branch->id, 'service_package_id' => $package->id])->save();
        $this->audit->record('service_package_price.saved', $price, ['package_id' => $package->id]);

        return $price;
    }

    public function disable(ServicePackage $package): void
    {
        if ($package->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Package is outside the current company.', status: 403);
        }
        $package->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('service_package.disabled', $package);
    }
}
