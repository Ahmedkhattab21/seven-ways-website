<?php

namespace App\Policies;

use App\Models\OpeningBalanceDocument;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class OpeningBalancePolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.opening_balances.view');
    }

    public function view(User $user, OpeningBalanceDocument $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.opening_balances.create');
    }

    public function update(User $user, OpeningBalanceDocument $model): bool
    {
        return $this->accountingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('accounting.opening_balances.update');
    }

    public function submit(User $user, OpeningBalanceDocument $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.opening_balances.submit');
    }

    public function approve(User $user, OpeningBalanceDocument $model): bool
    {
        return $this->accountingScoped($user, $model)
            && $user->isCompanyAdministrator()
            && $user->hasPermission('accounting.opening_balances.approve');
    }

    public function markReady(User $user, OpeningBalanceDocument $model): bool
    {
        return $this->accountingScoped($user, $model)
            && $user->isCompanyAdministrator()
            && $user->hasPermission('accounting.opening_balances.mark_ready');
    }
}
