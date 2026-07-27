<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\TreasuryApprovalLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TreasuryApprovalLimitService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(array $data, ?TreasuryApprovalLimit $limit = null): TreasuryApprovalLimit
    {
        return DB::transaction(function () use ($data, $limit) {
            if (empty($data['user_id']) === empty($data['role_id'])) {
                throw new BusinessRuleException('Approval limit must target exactly one user or role.');
            }
            if (isset($data['maximum_amount'])
                && bccomp((string) $data['minimum_amount'], (string) $data['maximum_amount'], 4) === 1) {
                throw new BusinessRuleException('Approval limit maximum must be greater than minimum.');
            }
            $query = TreasuryApprovalLimit::query()->where('company_id', $this->tenant->companyId())
                ->where('operation_type', $data['operation_type'])->where('currency_id', $data['currency_id'])
                ->where('branch_id', $data['branch_id'] ?? null)->where('user_id', $data['user_id'] ?? null)
                ->where('role_id', $data['role_id'] ?? null)->where('is_active', true)
                ->whereDate('valid_from', '<=', $data['valid_to'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $data['valid_from']));
            if ($limit) {
                $query->whereKeyNot($limit->id);
            }
            if ($query->lockForUpdate()->exists()) {
                throw new BusinessRuleException('Overlapping treasury approval limits are not allowed.');
            }
            $limit ??= new TreasuryApprovalLimit;
            $limit->fill($data);
            $limit->forceFill([
                'company_id' => $this->tenant->companyId(),
                'created_by' => $limit->created_by ?: $this->tenant->user()->id,
                'updated_by' => $limit->exists ? $this->tenant->user()->id : null,
            ])->save();
            $this->audit->record('treasury.approval_limit.updated', $limit, [
                'operation_type' => $limit->operation_type, 'approval_level' => $limit->approval_level,
            ]);
            DB::afterCommit(fn () => event(new \App\Events\TreasuryApprovalLimitUpdated($limit->id)));

            return $limit;
        });
    }

    public function assert(User $user, string $operation, string $ability, int $currencyId, string $amount, ?int $branchId): void
    {
        $column = 'can_'.$ability;
        if (! in_array($column, ['can_create', 'can_submit', 'can_approve', 'can_post'], true)) {
            throw new BusinessRuleException('Unsupported treasury approval ability.');
        }
        $roleIds = $user->roles()->pluck('roles.id');
        $limits = TreasuryApprovalLimit::query()->where('company_id', $this->tenant->companyId())
            ->where('operation_type', $operation)->where('currency_id', $currencyId)
            ->where('is_active', true)->whereDate('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()))
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('role_id', $roleIds))
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->get()->sortByDesc(fn ($limit) => [
                $limit->user_id === $user->id ? 1 : 0,
                $limit->branch_id === $branchId ? 1 : 0,
                $limit->approval_level,
            ]);
        $limit = $limits->first();
        if (! $limit || ! $limit->{$column}
            || bccomp($amount, (string) $limit->minimum_amount, 4) === -1
            || ($limit->maximum_amount !== null && bccomp($amount, (string) $limit->maximum_amount, 4) === 1)) {
            throw new BusinessRuleException('Treasury operation exceeds the actor approval limit.');
        }
        if ($limit->maximum_amount === null && ! $user->hasPermission('treasury.approval_limits.unlimited')) {
            throw new BusinessRuleException('Unlimited treasury approval requires explicit permission.');
        }
    }
}
