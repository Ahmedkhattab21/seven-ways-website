<?php

namespace App\Policies;

use App\Models\User;

class TrialBalancePolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('accounting.trial_balance.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('accounting.trial_balance.export');
    }
}
