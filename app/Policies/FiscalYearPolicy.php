<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class FiscalYearPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.fiscal_years.view');
    }

    public function view(User $user, FiscalYear $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.fiscal_years.create');
    }

    public function update(User $user, FiscalYear $model): bool
    {
        return $this->accountingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('accounting.fiscal_years.update');
    }

    public function open(User $user, FiscalYear $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.fiscal_years.open');
    }

    public function softClose(User $user, FiscalYear $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.fiscal_years.soft_close');
    }

    public function reopen(User $user, FiscalYear $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.fiscal_years.reopen');
    }
}
