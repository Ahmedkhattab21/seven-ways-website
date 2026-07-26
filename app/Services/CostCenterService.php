<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CostCenterCreated;
use App\Events\CostCenterDisabled;
use App\Events\CostCenterUpdated;
use App\Models\Branch;
use App\Models\CostCenter;
use Illuminate\Support\Facades\DB;

class CostCenterService
{
    public function __construct(
        private TenantContext $tenant,
        private CostCenterHierarchyService $hierarchy,
        private AuditService $audit
    ) {
    }

    public function save(CostCenter $center, array $data): CostCenter
    {
        return DB::transaction(function () use ($center, $data) {
            $companyId = $this->tenant->companyId();
            $parent = empty($data['parent_cost_center_id']) ? null : CostCenter::query()
                ->whereKey($data['parent_cost_center_id'])->where('company_id', $companyId)->firstOrFail();
            if ((bool) $data['is_header'] === (bool) $data['is_posting']) {
                throw new BusinessRuleException('Cost center must be either header or posting.');
            }
            if (! empty($data['branch_id'])) {
                $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id', $companyId)->firstOrFail();
                if (! $this->tenant->user()->canAccessBranch($branch)) {
                    throw new BusinessRuleException('Branch is outside the accessible scope.');
                }
                if ($parent?->branch_id && $parent->branch_id !== $branch->id) {
                    throw new BusinessRuleException('Branch cost center parent must belong to the same branch.');
                }
            }
            if ($center->exists && $center->is_system && ($center->code !== $data['code'] || $center->branch_id !== ($data['branch_id'] ?? null))) {
                throw new BusinessRuleException('System cost center identity is protected.');
            }
            $event = $center->exists ? CostCenterUpdated::class : CostCenterCreated::class;
            $center->forceFill($data + [
                'company_id' => $companyId, 'created_by' => $center->created_by ?: $this->tenant->user()->id,
                'updated_by' => $center->exists ? $this->tenant->user()->id : null,
            ])->save();
            $this->hierarchy->move($center, $parent);
            $this->audit->record($center->wasRecentlyCreated ? 'cost_center.created' : 'cost_center.updated', $center);
            DB::afterCommit(fn () => event(new $event($center->id)));

            return $center->fresh();
        });
    }

    public function disable(CostCenter $center): void
    {
        if ($center->company_id !== $this->tenant->companyId() || $center->is_system
            || $center->children()->where('is_active', true)->where('is_posting', true)->exists()) {
            throw new BusinessRuleException('Cost center cannot be disabled.');
        }
        $center->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('cost_center.disabled', $center);
        DB::afterCommit(fn () => event(new CostCenterDisabled($center->id)));
    }
}
