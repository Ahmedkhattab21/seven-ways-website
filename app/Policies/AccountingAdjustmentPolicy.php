<?php

namespace App\Policies;

use App\Models\AccountingAdjustment;
use App\Models\User;
use App\Policies\Concerns\ClosingPolicyScope;

class AccountingAdjustmentPolicy
{
    use ClosingPolicyScope;

    public function view(User $user, AccountingAdjustment $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.adjustments.view');
    }

    public function approve(User $user, AccountingAdjustment $model): bool
    {
        return $this->tenant($user, $model) && $model->created_by !== $user->id && $user->hasPermission('accounting.adjustments.approve');
    }
}
