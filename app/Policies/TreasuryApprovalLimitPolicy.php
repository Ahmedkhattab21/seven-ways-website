<?php

namespace App\Policies;

use App\Models\TreasuryApprovalLimit;
use App\Models\User;

class TreasuryApprovalLimitPolicy
{
    public function view(User $user, TreasuryApprovalLimit $limit): bool
    {
        return $limit->company_id === $user->company_id
            && $user->hasPermission('treasury.approval_limits.view');
    }

    public function update(User $user, TreasuryApprovalLimit $limit): bool
    {
        return $limit->company_id === $user->company_id
            && $user->hasPermission('treasury.approval_limits.manage');
    }
}
