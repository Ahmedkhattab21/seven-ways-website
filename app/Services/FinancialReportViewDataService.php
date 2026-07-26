<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CostCenter;
use App\Models\FiscalYear;

class FinancialReportViewDataService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function filters(): array
    {
        $companyId = $this->tenant->companyId();

        return [
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_posting', true)->orderBy('account_code')->get(),
            'branches' => $this->tenant->accessibleBranches(),
            'periods' => AccountingPeriod::query()->where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'years' => FiscalYear::query()->where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'costCenters' => CostCenter::query()->where('company_id', $companyId)->where('is_posting', true)->orderBy('code')->get(),
        ];
    }
}
