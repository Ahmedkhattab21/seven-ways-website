<?php

namespace App\Policies;

use App\Models\CashBox;
use App\Models\User;

class CashBoxPolicy
{
    public function view(User $user, CashBox $box): bool
    {
        return $box->company_id === $user->company_id && $user->canAccessBranch($box->branch)
            && $user->hasPermission('treasury.cash_boxes.view');
    }

    public function update(User $user, CashBox $box): bool
    {
        return $box->company_id === $user->company_id && $user->canAccessBranch($box->branch)
            && $box->status !== 'closed' && $user->hasPermission('treasury.cash_boxes.update');
    }
}
