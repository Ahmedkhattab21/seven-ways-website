<?php

namespace App\Policies;

use App\Models\AccountingClosingRun;
use App\Models\User;
use App\Policies\Concerns\ClosingPolicyScope;

class AccountingClosingRunPolicy
{
    use ClosingPolicyScope;

    public function view(User $user, AccountingClosingRun $run): bool
    {
        return $this->tenant($user, $run) && $user->hasPermission('accounting.closing.view');
    }

    public function approve(User $user, AccountingClosingRun $run): bool
    {
        return $this->tenant($user, $run) && $run->started_by !== $user->id && $user->hasPermission('accounting.closing.approve');
    }
}
