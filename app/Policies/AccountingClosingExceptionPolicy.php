<?php

namespace App\Policies;

use App\Models\AccountingClosingException;
use App\Models\User;
use App\Policies\Concerns\ClosingPolicyScope;

class AccountingClosingExceptionPolicy
{
    use ClosingPolicyScope;

    public function view(User $user, AccountingClosingException $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.closing_exceptions.view');
    }

    public function waive(User $user, AccountingClosingException $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.closing_exceptions.waive');
    }
}
