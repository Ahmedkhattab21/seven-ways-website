<?php

namespace App\Policies;

use App\Models\CashBoxCustodian;
use App\Models\User;

class CashBoxCustodianPolicy
{
    public function view(User $user, CashBoxCustodian $custodian): bool
    {
        return $custodian->company_id === $user->company_id
            && $user->canAccessBranch($custodian->cashBox->branch)
            && $user->hasPermission('treasury.cash_boxes.view');
    }

    public function update(User $user, CashBoxCustodian $custodian): bool
    {
        return $custodian->company_id === $user->company_id
            && $user->hasPermission('treasury.cash_boxes.manage_custodians');
    }
}
