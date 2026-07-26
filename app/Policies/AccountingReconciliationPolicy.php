<?php

namespace App\Policies;

use App\Models\User;

class AccountingReconciliationPolicy
{
    public function customers(User $user): bool
    {
        return $user->hasPermission('accounting.reconciliation.customers');
    }

    public function suppliers(User $user): bool
    {
        return $user->hasPermission('accounting.reconciliation.suppliers');
    }

    public function inventory(User $user): bool
    {
        return $user->hasPermission('accounting.reconciliation.inventory');
    }

    public function tax(User $user): bool
    {
        return $user->hasPermission('accounting.reconciliation.tax');
    }
}
