<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\ApprovalDelegation;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalDelegationService
{
    public function __construct(private TenantContext $tenant, private UnifiedAuditService $audit)
    {
    }

    public function create(array $data): ApprovalDelegation
    {
        return DB::transaction(function () use ($data) {
            $delegator = User::where('company_id', $this->tenant->companyId())->findOrFail($data['delegator_id']);
            $delegate = User::where('company_id', $this->tenant->companyId())->findOrFail($data['delegate_id']);
            if ($delegator->id === $delegate->id || ! $delegator->isActive() || ! $delegate->isActive()) {
                throw new BusinessRuleException('Delegation users must be different active users.');
            }
            if (! $this->tenant->user()->isCompanyAdministrator() && $delegator->id !== $this->tenant->user()->id) {
                abort(403);
            }
            $branchId = $data['branch_id'] ?? null;
            if ($branchId) {
                $branch = Branch::where('company_id', $this->tenant->companyId())->findOrFail($branchId);
                if (! $delegator->canAccessBranch($branch) || ! $delegate->canAccessBranch($branch)) {
                    throw new BusinessRuleException('Both users must have access to the delegated branch.');
                }
            }
            if ($this->createsCycle($delegator->id, $delegate->id)) {
                throw new BusinessRuleException('Circular delegation is not allowed.');
            }
            if (ApprovalDelegation::where('company_id', $this->tenant->companyId())
                ->where('delegator_id', $delegator->id)->where('delegate_id', $delegate->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', $data['ends_at'])->where('ends_at', '>=', $data['starts_at'])
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->lockForUpdate()->exists()) {
                throw new BusinessRuleException('An overlapping delegation already exists.');
            }
            foreach ($data['modules'] as $module) {
                $permission = match ($module) {
                    'purchasing' => ['purchase_requisitions.approve', 'purchase_orders.approve'],
                    'treasury' => ['treasury.transfers.approve'],
                    default => [],
                };
                if (! collect($permission)->contains(fn ($name) => $delegator->hasPermission($name))) {
                    throw new BusinessRuleException('Delegator does not own the requested module authority.');
                }
            }
            $delegation = new ApprovalDelegation($data);
            $delegation->forceFill([
                'company_id' => $this->tenant->companyId(), 'status' => 'active',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('delegation.created', 'approvals', 'create', $delegation, [
                'branch_id' => $branchId, 'new_values' => [
                    'delegator_id' => $delegator->id, 'delegate_id' => $delegate->id,
                    'modules' => $data['modules'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                ],
            ]);

            return $delegation;
        });
    }

    public function cancel(ApprovalDelegation $delegation): ApprovalDelegation
    {
        return DB::transaction(function () use ($delegation) {
            $delegation = ApprovalDelegation::where('company_id', $this->tenant->companyId())
                ->whereKey($delegation->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $this->tenant->user()->isCompanyAdministrator() || $delegation->delegator_id === $this->tenant->user()->id,
                403
            );
            if ($delegation->status !== 'active') {
                throw new BusinessRuleException('Only active delegations can be cancelled.');
            }
            $delegation->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()->id, 'cancelled_at' => now(),
            ])->save();
            $this->audit->record('delegation.cancelled', 'approvals', 'cancel', $delegation);

            return $delegation;
        });
    }

    private function createsCycle(int $delegatorId, int $delegateId): bool
    {
        $seen = [];
        $queue = [$delegateId];
        while ($queue) {
            $current = array_shift($queue);
            if ($current === $delegatorId) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $queue = array_merge($queue, ApprovalDelegation::where('company_id', $this->tenant->companyId())
                ->where('delegator_id', $current)->where('status', 'active')
                ->where('starts_at', '<=', now())->where('ends_at', '>=', now())->pluck('delegate_id')->all());
        }

        return false;
    }
}
