<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Support\Facades\DB;

class ServicePackageService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function save(array $data, array $items, ?ServicePackage $package = null): ServicePackage
    {
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

        return DB::transaction(function () use ($data, $items, $package) {
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
            $this->audit->record($package->wasRecentlyCreated ? 'service_package.created' : 'service_package.updated', $package);

            return $package;
        });
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
