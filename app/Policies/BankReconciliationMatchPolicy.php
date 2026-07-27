<?php

namespace App\Policies;

use App\Models\BankReconciliationMatch;
use App\Models\User;

class BankReconciliationMatchPolicy
{
    public function update(User $user, BankReconciliationMatch $match): bool
    {
        return $match->company_id === $user->company_id
            && ! in_array($match->session->status, ['completed', 'cancelled'], true)
            && $user->hasPermission('treasury.reconciliation.match');
    }
}
