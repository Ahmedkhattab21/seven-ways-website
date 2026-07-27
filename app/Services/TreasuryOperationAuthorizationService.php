<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\TreasuryApprovalLimit;
use Illuminate\Auth\Access\AuthorizationException;

class TreasuryOperationAuthorizationService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryApprovalLimitService $limits
    ) {
    }

    public function assert(
        string $permission,
        string $operation,
        string $ability,
        int $currencyId,
        string $amount,
        ?int $branchId,
        ?int $creatorId = null
    ): void {
        $user = $this->tenant->user();
        if (! $user->hasPermission($permission)) {
            throw new BusinessRuleException('Treasury permission is required.');
        }
        if ($branchId) {
            $branch = Branch::query()->where('company_id', $this->tenant->companyId())->findOrFail($branchId);
            if (! $user->canAccessBranch($branch)) {
                throw new AuthorizationException('Treasury branch is outside the actor scope.');
            }
        }
        if ($ability === 'approve' && $creatorId === $user->id) {
            throw new BusinessRuleException('Separation of duties prevents self-approval.');
        }
        if (TreasuryApprovalLimit::query()->where('company_id', $this->tenant->companyId())
            ->where('operation_type', $operation)->where('currency_id', $currencyId)
            ->where('is_active', true)->exists()) {
            $this->limits->assert($user, $operation, $ability, $currencyId, $amount, $branchId);
        }
    }
}
