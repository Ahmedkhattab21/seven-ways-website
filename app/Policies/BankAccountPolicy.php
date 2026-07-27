<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function view(User $user, BankAccount $account): bool
    {
        if ($account->company_id !== $user->company_id || ! $user->hasPermission('treasury.bank_accounts.view')) {
            return false;
        }
        if ($user->isCompanyAdministrator() || $user->hasRole(['finance_manager', 'accountant'])) {
            return true;
        }
        if ($account->branch_id) {
            return $user->canAccessBranch($account->branch);
        }

        return $account->branchAccess()->where('branch_id', $user->branch_id)
            ->where('can_view', true)->where('is_active', true)->exists();
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $account->company_id === $user->company_id
            && $account->status !== 'closed'
            && $user->hasPermission('treasury.bank_accounts.update');
    }
}
