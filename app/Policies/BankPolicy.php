<?php

namespace App\Policies;

use App\Models\Bank;
use App\Models\User;

class BankPolicy
{
    public function view(User $user, Bank $bank): bool
    {
        return ($bank->company_id === null || $bank->company_id === $user->company_id)
            && $user->hasPermission('treasury.banks.view');
    }

    public function update(User $user, Bank $bank): bool
    {
        return ! $bank->is_system && $bank->company_id === $user->company_id
            && $user->hasPermission('treasury.banks.manage');
    }
}
