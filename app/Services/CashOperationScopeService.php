<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\CashBox;
use App\Models\CashBoxSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CashOperationScopeService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function branch(int $branchId): ?Branch
    {
        return $this->tenant->accessibleBranches()->firstWhere('id', $branchId);
    }

    public function requireBranch(int $branchId): Branch
    {
        return $this->branch($branchId)
            ?? throw new BusinessRuleException('Branch is outside the accessible scope.');
    }

    public function cashBoxes(string $direction, int $branchId): Collection
    {
        $this->requireDirection($direction);
        $branch = $this->requireBranch($branchId);

        return CashBox::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where($direction === 'receipt' ? 'allows_receipts' : 'allows_payments', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function sessions(int $branchId, ?int $cashBoxId = null): Collection
    {
        $branch = $this->requireBranch($branchId);

        return CashBoxSession::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->when($cashBoxId, fn (Builder $query) => $query->where('cash_box_id', $cashBoxId))
            ->where('active_guard', 'active')
            ->where('status', 'counting')
            ->whereHas('counts', fn (Builder $query) => $query
                ->where('count_type', 'opening')->where('status', 'approved'))
            ->with('cashBox:id,code,name')
            ->orderByDesc('id')
            ->get();
    }

    public function accounts(string $direction, int $branchId): Collection
    {
        return $this->accountQuery($direction, $branchId)
            ->with('type:id,classification')
            ->orderBy('account_code')
            ->get();
    }

    public function account(string $direction, int $branchId, int $accountId): ?Account
    {
        return $this->accountQuery($direction, $branchId)->find($accountId);
    }

    private function accountQuery(string $direction, int $branchId): Builder
    {
        $this->requireDirection($direction);
        $branch = $this->requireBranch($branchId);
        [$currentBranchAccounts, $allMappedAccounts] = $this->branchMappings($branch);
        $canUseControlAccounts = $this->tenant->user()
            ->hasPermission('accounting.journals.post_control_accounts');

        return Account::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->where('is_posting', true)
            ->where('is_cash_account', false)
            ->where('is_bank_account', false)
            ->where(function (Builder $query) use ($canUseControlAccounts, $direction) {
                $query->where('allow_manual_entry', true);
                if ($canUseControlAccounts) {
                    $query->orWhere(function (Builder $controlQuery) use ($direction) {
                        $controlQuery->whereIn('control_type', $this->allowedControlTypes($direction));
                    });
                }
            })
            ->when($allMappedAccounts !== [], function (Builder $query) use ($currentBranchAccounts, $allMappedAccounts) {
                $query->where(function (Builder $branchQuery) use ($currentBranchAccounts, $allMappedAccounts) {
                    $branchQuery->where('requires_branch', false)
                        ->orWhereIn('id', $currentBranchAccounts)
                        ->orWhereNotIn('id', $allMappedAccounts);
                });
            });
    }

    private function branchMappings(Branch $branch): array
    {
        $settings = BranchAccountingSetting::query()
            ->where('company_id', $branch->company_id)
            ->get(array_merge(['branch_id'], BranchAccountingSettingsService::ACCOUNT_COLUMNS));
        $mapped = fn (BranchAccountingSetting $setting) => collect(BranchAccountingSettingsService::ACCOUNT_COLUMNS)
            ->map(fn (string $column) => $setting->getAttribute($column))
            ->filter()
            ->map(fn ($id) => (int) $id);

        return [
            $settings->where('branch_id', $branch->id)->flatMap($mapped)->unique()->values()->all(),
            $settings->flatMap($mapped)->unique()->values()->all(),
        ];
    }

    private function allowedControlTypes(string $direction): array
    {
        return $direction === 'receipt'
            ? ['accounts_receivable', 'customer_advances', 'supplier_advances', 'employee_advances']
            : ['accounts_payable', 'supplier_advances', 'customer_advances', 'employee_advances', 'employee_payables'];
    }

    private function requireDirection(string $direction): void
    {
        if (! in_array($direction, ['receipt', 'payment'], true)) {
            throw new BusinessRuleException('Unsupported cash operation direction.');
        }
    }
}
