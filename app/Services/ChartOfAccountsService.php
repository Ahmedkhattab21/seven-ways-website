<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AccountCreated;
use App\Events\AccountDisabled;
use App\Events\AccountHierarchyChanged;
use App\Events\AccountUpdated;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\BranchAccountingSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsService
{
    public function __construct(
        private TenantContext $tenant,
        private AccountHierarchyService $hierarchy,
        private AuditService $audit
    ) {
    }

    public function save(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data) {
            $companyId = $this->tenant->companyId();
            $type = AccountType::query()->whereKey($data['account_type_id'])
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
                ->where('is_active', true)->firstOrFail();
            $group = empty($data['account_group_id']) ? null : AccountGroup::query()
                ->whereKey($data['account_group_id'])->where('company_id', $companyId)->firstOrFail();
            $parent = empty($data['parent_account_id']) ? null : Account::query()
                ->whereKey($data['parent_account_id'])->where('company_id', $companyId)->lockForUpdate()->firstOrFail();
            if ($group && $group->account_type_id !== $type->id) {
                throw new BusinessRuleException('Account group and account type are incompatible.');
            }
            if ((bool) $data['is_header'] === (bool) $data['is_posting']) {
                throw new BusinessRuleException('Account must be either header or posting.');
            }
            if ($account->exists && $account->is_system && $this->changesProtectedSystemFields($account, $data)) {
                throw new BusinessRuleException('System account classification is protected.');
            }
            if ($account->exists && $data['is_posting'] && $account->children()->exists()) {
                throw new BusinessRuleException('A header with children cannot become a posting account.');
            }
            $candidate = clone $account;
            $candidate->forceFill(['company_id' => $companyId, 'account_type_id' => $type->id]);
            $this->hierarchy->assertParent($candidate, $parent);
            $normal = $data['normal_balance'] ?? $type->normal_balance;
            if ($normal !== $type->normal_balance && empty($data['contra_reason'])) {
                throw new BusinessRuleException('Contra normal balance requires a documented reason.');
            }
            if (! empty($data['currency_id']) && ! $data['allows_multi_currency']) {
                throw new BusinessRuleException('اختيار عملة محددة للحساب يتطلب تفعيل خيار متعدد العملات.');
            }
            $this->assertCashAccountRules($account, $data, $type);
            $event = $account->exists ? AccountUpdated::class : AccountCreated::class;
            $account->forceFill(array_diff_key($data, ['contra_reason' => true]) + [
                'company_id' => $companyId, 'account_type_id' => $type->id,
                'normal_balance' => $normal,
                'created_by' => $account->created_by ?: $this->tenant->user()->id,
                'updated_by' => $account->exists ? $this->tenant->user()->id : null,
            ])->save();
            $this->hierarchy->refreshPath($account);
            $this->audit->record($account->wasRecentlyCreated ? 'account.created' : 'account.updated', $account);
            DB::afterCommit(fn () => event(new $event($account->id)));

            return $account->fresh();
        });
    }

    public function move(Account $account, ?Account $parent): Account
    {
        $this->assertTenant($account);
        $moved = $this->hierarchy->move($account, $parent);
        $this->audit->record('account.hierarchy_changed', $moved, ['parent_account_id' => $parent?->id]);
        DB::afterCommit(fn () => event(new AccountHierarchyChanged($moved->id)));

        return $moved;
    }

    public function disable(Account $account): void
    {
        $this->assertTenant($account);
        if ($account->is_system || $account->children()->where('is_active', true)->exists()) {
            throw new BusinessRuleException('System accounts and headers with active children cannot be disabled.');
        }
        $mapped = BranchAccountingSetting::query()->where('company_id', $account->company_id)
            ->where(function ($query) use ($account) {
                foreach (BranchAccountingSettingsService::ACCOUNT_COLUMNS as $column) {
                    $query->orWhere($column, $account->id);
                }
            })->exists();
        if ($mapped) {
            throw new BusinessRuleException('Mapped accounts require a replacement before disabling.');
        }
        $account->forceFill(['is_active' => false, 'updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('account.disabled', $account);
        DB::afterCommit(fn () => event(new AccountDisabled($account->id)));
    }

    public function tree(): Collection
    {
        return Account::query()->where('company_id', $this->tenant->companyId())
            ->when(! $this->tenant->user()->hasPermission('accounting.accounts.view_sensitive'), fn ($query) => $query->where('is_control_account', false))
            ->whereNull('parent_account_id')->with('children.children.children')->orderBy('account_code')->get();
    }

    public function postingAccounts(): Collection
    {
        return Account::query()->where('company_id', $this->tenant->companyId())
            ->when(! $this->tenant->user()->hasPermission('accounting.accounts.view_sensitive'), fn ($query) => $query->where('is_control_account', false))
            ->where('is_active', true)->where('is_posting', true)->orderBy('account_code')->get();
    }

    private function assertTenant(Account $account): void
    {
        if ($account->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Account is outside the current company.');
        }
    }

    private function assertCashAccountRules(Account $account, array $data, AccountType $type): void
    {
        if (! (bool) ($data['is_cash_account'] ?? false)) {
            return;
        }

        $isActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($account->exists ? (bool) $account->is_active : true);

        if (! (bool) $data['is_posting'] || (bool) $data['is_header']) {
            throw new BusinessRuleException('الحساب النقدي يجب أن يكون حساب حركة وليس حسابًا رئيسيًا.');
        }
        if (! $isActive) {
            throw new BusinessRuleException('الحساب النقدي يجب أن يكون نشطًا.');
        }
        if ($type->code !== 'ASSET') {
            throw new BusinessRuleException('الحساب النقدي يجب أن يكون من نوع الأصول.');
        }
        if ((bool) ($data['is_control_account'] ?? false)) {
            throw new BusinessRuleException('لا يمكن تعريف الحساب النقدي كحساب رقابي.');
        }
        if ((bool) ($data['is_bank_account'] ?? false)) {
            throw new BusinessRuleException('لا يمكن تعريف الحساب نفسه كحساب نقدي وحساب بنكي.');
        }
    }

    private function changesProtectedSystemFields(Account $account, array $data): bool
    {
        return $account->account_code !== $data['account_code']
            || $account->account_type_id !== (int) $data['account_type_id']
            || $account->normal_balance !== ($data['normal_balance'] ?? $account->normal_balance);
    }
}
