<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class ServiceCatalogService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function saveCategory(array $data, ?ServiceCategory $category = null): ServiceCategory
    {
        return DB::transaction(function () use ($data, $category) {
            $category ??= new ServiceCategory;
            $this->assertOwned($category);
            if (! empty($data['parent_id'])) {
                $parent = ServiceCategory::query()->whereKey($data['parent_id'])
                    ->where('company_id', $this->tenant->companyId())->firstOrFail();
                if ($category->exists && ($parent->is($category) || $this->hasAncestor($parent, $category->id))) {
                    throw new BusinessRuleException('Circular service category relationships are not allowed.');
                }
            }
            $category->fill($data)->forceFill([
                'company_id' => $this->tenant->companyId(),
                $category->exists ? 'updated_by' : 'created_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record($category->wasRecentlyCreated ? 'service_category.created' : 'service_category.updated', $category);

            return $category;
        });
    }

    public function disableCategory(ServiceCategory $category): void
    {
        $this->assertOwned($category);
        if ($category->children()->where('is_active', true)->exists() || $category->services()->where('is_active', true)->exists()) {
            throw new BusinessRuleException('A category with active children or services cannot be disabled.');
        }
        $category->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('service_category.disabled', $category);
    }

    public function saveService(array $data, ?Service $service = null): Service
    {
        return DB::transaction(function () use ($data, $service) {
            $service ??= new Service;
            $this->assertOwned($service);
            $branch = null;
            if (! empty($data['branch_id'])) {
                $branch = Branch::query()
                    ->whereKey($data['branch_id'])
                    ->where('company_id', $this->tenant->companyId())
                    ->where('is_active', true)
                    ->firstOrFail();
                if (! $this->tenant->user()?->canAccessBranch($branch)) {
                    throw new BusinessRuleException('Branch is outside your access scope.', status: 403);
                }
            }
            unset($data['branch_id']);
            $category = ServiceCategory::query()->whereKey($data['service_category_id'])
                ->where('company_id', $this->tenant->companyId())->where('is_active', true)->firstOrFail();
            if (! empty($data['default_tax_id'])) {
                Tax::query()->whereKey($data['default_tax_id'])->where('company_id', $this->tenant->companyId())
                    ->where('is_active', true)->firstOrFail();
            }
            if (($data['pricing_type'] ?? null) === 'per_unit') {
                if (empty($data['pricing_unit_id'])) {
                    throw new BusinessRuleException('Per-unit services require a pricing unit.');
                }
                Unit::query()->whereKey($data['pricing_unit_id'])
                    ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $this->tenant->companyId()))
                    ->where('is_active', true)->firstOrFail();
            }
            if ($service->exists && $service->pricing_type !== $data['pricing_type'] && $service->prices()->exists()) {
                throw new BusinessRuleException('Pricing type cannot change while service prices exist.');
            }
            if (empty($data['code'])) {
                $data['code'] = $this->numbers->next('service', $this->tenant->companyId(), null);
            }
            $service->fill($data)->forceFill([
                'company_id' => $category->company_id,
                $service->exists ? 'updated_by' : 'created_by' => $this->tenant->user()?->id,
            ])->save();
            if ($branch) {
                $availability = BranchService::query()->firstOrNew([
                    'branch_id' => $branch->id,
                    'service_id' => $service->id,
                ]);
                if (! $availability->exists) {
                    $availability->forceFill([
                        'company_id' => $service->company_id,
                        'branch_id' => $branch->id,
                        'service_id' => $service->id,
                        'is_available' => true,
                        'booking_enabled' => true,
                        'requires_approval' => false,
                        'default_duration_minutes' => $service->default_duration_minutes,
                        'is_active' => true,
                    ])->save();
                }
                if ($availability->wasRecentlyCreated) {
                    $this->audit->record('branch_service.created', $availability, [
                        'branch_id' => $branch->id,
                        'service_id' => $service->id,
                    ]);
                }
            }
            $this->audit->record($service->wasRecentlyCreated ? 'service.created' : 'service.updated', $service);

            return $service;
        });
    }

    public function disable(Service $service): void
    {
        $this->assertOwned($service);
        $service->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('service.disabled', $service);
    }

    private function assertOwned(Service|ServiceCategory $model): void
    {
        if ($model->exists && $model->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Record is outside the current company.', status: 403);
        }
    }

    private function hasAncestor(ServiceCategory $category, int $id): bool
    {
        while ($category->parent_id) {
            if ($category->parent_id === $id) {
                return true;
            }
            $category = ServiceCategory::query()->find($category->parent_id);
            if (! $category) {
                break;
            }
        }

        return false;
    }
}
