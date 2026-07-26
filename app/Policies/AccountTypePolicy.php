<?php

namespace App\Policies;

use App\Models\AccountType;
use App\Models\User;

class AccountTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.account_types.view');
    }

    public function view(User $user, AccountType $model): bool
    {
        return ($model->company_id === null || $model->company_id === $user->company_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.account_types.manage');
    }

    public function update(User $user, AccountType $model): bool
    {
        return ! $model->is_system && $model->company_id === $user->company_id && $this->create($user);
    }
}
