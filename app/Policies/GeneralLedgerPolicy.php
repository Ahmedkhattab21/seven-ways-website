<?php

namespace App\Policies;

use App\Models\User;

class GeneralLedgerPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('accounting.general_ledger.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('accounting.general_ledger.export');
    }
}
