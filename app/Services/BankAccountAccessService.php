<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankAccountAccessChanged;
use App\Models\BankAccount;
use App\Models\BankAccountBranchAccess;
use Illuminate\Support\Facades\DB;

class BankAccountAccessService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryScopeService $scope,
        private AuditService $audit
    ) {
    }

    public function save(BankAccount $account, array $data): BankAccountBranchAccess
    {
        return DB::transaction(function () use ($account, $data) {
            $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($account->status === 'closed') {
                throw new BusinessRuleException('Closed account access cannot be changed.');
            }
            $branch = $this->scope->branch((int) $data['branch_id']);
            $access = BankAccountBranchAccess::query()->lockForUpdate()->firstOrNew([
                'bank_account_id' => $account->id, 'branch_id' => $branch->id,
            ]);
            $access->fill($data);
            $access->forceFill(['company_id' => $account->company_id])->save();
            $this->audit->record('treasury.bank_account.access_changed', $account, [
                'branch_id' => $branch->id, 'is_active' => $access->is_active,
            ]);
            DB::afterCommit(fn () => event(new BankAccountAccessChanged($account->id)));

            return $access;
        });
    }

    public function assert(BankAccount $account, int $branchId, string $ability, ?string $amount = null): void
    {
        if ($account->branch_id && $account->branch_id !== $branchId) {
            throw new BusinessRuleException('Bank account is restricted to another branch.');
        }
        $access = BankAccountBranchAccess::query()->where('company_id', $account->company_id)
            ->where('bank_account_id', $account->id)->where('branch_id', $branchId)
            ->where('is_active', true)->first();
        if (! $access || ! $access->{$ability}) {
            throw new BusinessRuleException('Branch is not authorized for this bank operation.');
        }
        $limit = match ($ability) {
            'can_pay' => $access->daily_payment_limit,
            'can_transfer' => $access->daily_transfer_limit,
            default => null,
        };
        if ($amount !== null && $limit !== null && bccomp($amount, (string) $limit, 4) === 1) {
            throw new BusinessRuleException('Treasury operation exceeds the branch daily limit.');
        }
    }
}
