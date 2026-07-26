<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class AccountPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.accounts.view');
    }

    public function view(User $user, Account $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user)
            && (! $model->is_control_account || $user->hasPermission('accounting.accounts.view_sensitive'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.accounts.create');
    }

    public function update(User $user, Account $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.accounts.update');
    }

    public function disable(User $user, Account $model): bool
    {
        return $this->accountingScoped($user, $model) && ! $model->is_system && $user->hasPermission('accounting.accounts.disable');
    }

    public function move(User $user, Account $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.accounts.move');
    }
}
