<?php

namespace App\Policies;

use App\Models\AccountingClosingChecklist;
use App\Models\User;
use App\Policies\Concerns\ClosingPolicyScope;

class AccountingClosingChecklistPolicy
{
    use ClosingPolicyScope;

    public function view(User $user, AccountingClosingChecklist $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.closing.view');
    }
}
