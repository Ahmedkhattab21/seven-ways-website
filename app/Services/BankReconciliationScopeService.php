<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BankReconciliationScopeService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function accountQuery(): Builder
    {
        $query = BankAccount::query()->where('company_id', $this->tenant->companyId());
        if ($this->tenant->user()->isCompanyAdministrator()) {
            return $query;
        }
        $branchId = $this->tenant->branchId();

        return $query->where(function (Builder $scope) use ($branchId) {
            $scope->where('branch_id', $branchId)->orWhere(function (Builder $shared) use ($branchId) {
                $shared->whereNull('branch_id')->whereHas('branchAccess', fn (Builder $access) => $access
                    ->where('branch_id', $branchId)->where('is_active', true)->where('can_view', true));
            });
        });
    }

    public function accountIds(): Collection
    {
        return $this->accountQuery()->pluck('id');
    }
}
