<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class AccountingPeriodPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.periods.view');
    }

    public function view(User $user, AccountingPeriod $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.periods.create');
    }

    public function update(User $user, AccountingPeriod $model): bool
    {
        return $this->accountingScoped($user, $model) && $model->status !== 'locked' && $user->hasPermission('accounting.periods.update');
    }
}
