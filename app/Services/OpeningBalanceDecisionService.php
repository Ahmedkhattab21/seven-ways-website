<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class OpeningBalanceDecisionService
{
    public function __construct(
        private TenantContext $tenant,
        private AuditService $audit
    ) {
    }

    public function startFromZero(Company $company): Company
    {
        $user = $this->tenant->user();
        if (! $user
            || $user->company_id !== $company->id
            || ! $user->isCompanyAdministrator()
            || ! $user->hasPermission('companies.update')) {
            throw new AuthorizationException();
        }

        return DB::transaction(function () use ($company) {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $before = $company->opening_balances_decision;
            $company->forceFill(['opening_balances_decision' => 'start_from_zero'])->save();

            if ($before !== 'start_from_zero') {
                $this->audit->record('company.opening_balances_decision_changed', $company, [
                    'before' => $before,
                    'after' => 'start_from_zero',
                ]);
            }

            return $company;
        });
    }
}
