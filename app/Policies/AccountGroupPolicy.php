<?php

namespace App\Policies;

use App\Models\AccountGroup;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class AccountGroupPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.account_groups.view');
    }

    public function view(User $user, AccountGroup $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.account_groups.create');
    }

    public function update(User $user, AccountGroup $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.account_groups.update');
    }

    public function disable(User $user, AccountGroup $model): bool
    {
        return $this->accountingScoped($user, $model) && ! $model->is_system && $user->hasPermission('accounting.account_groups.disable');
    }
}
