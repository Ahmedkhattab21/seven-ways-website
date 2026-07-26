<?php

namespace App\Policies;

use App\Models\AccountingSetting;
use App\Models\User;

class AccountingSettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.settings.view');
    }

    public function view(User $user, AccountingSetting $model): bool
    {
        return $model->company_id === $user->company_id && $this->viewAny($user);
    }

    public function update(User $user, AccountingSetting $model): bool
    {
        return $model->company_id === $user->company_id && $user->hasPermission('accounting.settings.update');
    }
}
