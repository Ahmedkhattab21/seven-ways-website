<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Currency;

class TreasuryScopeService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function branch(?int $id): ?Branch
    {
        if (! $id) {
            return null;
        }
        $branch = Branch::query()->where('company_id', $this->tenant->companyId())->findOrFail($id);
        if (! $this->tenant->user()->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch is outside the current treasury scope.');
        }

        return $branch;
    }

    public function bank(int $id): Bank
    {
        return Bank::query()->where(function ($query) {
            $query->whereNull('company_id')->orWhere('company_id', $this->tenant->companyId());
        })->where('is_active', true)->findOrFail($id);
    }

    public function currency(int $id): Currency
    {
        $currency = Currency::query()->where('is_active', true)->findOrFail($id);
        $company = $this->tenant->company();
        if ($company->currency_id !== $currency->id) {
            throw new BusinessRuleException('Currency is not enabled for the current company.');
        }

        return $currency;
    }

    public function account(int $id, ?string $treasuryType = null): Account
    {
        $account = Account::query()->where('company_id', $this->tenant->companyId())
            ->where('is_active', true)->where('is_posting', true)->findOrFail($id);
        if ($treasuryType === 'bank' && ! $account->is_bank_account) {
            throw new BusinessRuleException('Bank account must use an active posting bank GL account.');
        }
        if ($treasuryType === 'cash' && ! $account->is_cash_account) {
            throw new BusinessRuleException('Cash box must use an active posting cash GL account.');
        }

        return $account;
    }
}
