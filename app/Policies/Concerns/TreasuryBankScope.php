<?php

namespace App\Policies\Concerns;

use App\Models\BankAccount;
use App\Models\User;

trait TreasuryBankScope
{
    protected function bankScope(User $user, BankAccount $account): bool
    {
        if ($account->company_id !== $user->company_id) {
            return false;
        }
        if ($user->isCompanyAdministrator()) {
            return true;
        }
        if ($account->branch_id) {
            return $account->branch && $user->canAccessBranch($account->branch);
        }
        $branchIds = $user->accessibleBranches()->wherePivot('can_view', true)->pluck('branches.id');
        if ($user->branch_id) {
            $branchIds->push($user->branch_id);
        }

        return $account->branchAccess()->where('is_active', true)->where('can_view', true)
            ->whereIn('branch_id', $branchIds->unique())->exists();
    }
}
