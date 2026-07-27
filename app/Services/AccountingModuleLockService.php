<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;

class AccountingModuleLockService
{
    public const MODULES = ['sales', 'purchasing', 'inventory', 'payments', 'treasury', 'manual_journals', 'opening_balances', 'adjustments'];

    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function update(AccountingPeriod $period, array $modules, string $reason): AccountingPeriod
    {
        if ($period->company_id !== $this->tenant->companyId() || ! in_array($period->status, ['open', 'soft_closed'], true)) {
            throw new BusinessRuleException('Module locks can only change on an open or soft-closed tenant period.');
        }
        $invalid = array_diff($modules, self::MODULES);
        if ($invalid !== [] || blank($reason)) {
            throw new BusinessRuleException('Module locks and reason are invalid.');
        }

        return DB::transaction(function () use ($period, $modules, $reason) {
            $period = AccountingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $before = $period->locked_modules ?? [];
            $period->forceFill(['locked_modules' => array_values(array_unique($modules))])->save();
            $this->audit->record('accounting_period.module_locks_changed', $period, compact('before', 'modules', 'reason'));

            return $period;
        });
    }
}
