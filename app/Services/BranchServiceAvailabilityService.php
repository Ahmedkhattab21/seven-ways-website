<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class BranchServiceAvailabilityService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(Service $service, Branch $branch, array $data): BranchService
    {
        if ($service->company_id !== $this->tenant->companyId() || $branch->company_id !== $service->company_id) {
            throw new BusinessRuleException('Service and branch must belong to the current company.', status: 403);
        }
        if (! $this->tenant->user()?->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch is outside your access scope.', status: 403);
        }
        if (! $branch->is_active && ($data['is_available'] ?? false)) {
            throw new BusinessRuleException('An inactive branch cannot enable a service.');
        }
        if (isset($data['minimum_price'], $data['default_price'])
            && bccomp((string) $data['minimum_price'], (string) $data['default_price'], 4) === 1) {
            throw new BusinessRuleException('Minimum price cannot exceed the branch default price.');
        }

        return DB::transaction(function () use ($service, $branch, $data) {
            $record = BranchService::query()->firstOrNew(['branch_id' => $branch->id, 'service_id' => $service->id]);
            $record->fill($data)->forceFill(['company_id' => $service->company_id])->save();
            $this->audit->record('branch_service.updated', $record, ['branch_id' => $branch->id, 'service_id' => $service->id]);

            return $record;
        });
    }
}
