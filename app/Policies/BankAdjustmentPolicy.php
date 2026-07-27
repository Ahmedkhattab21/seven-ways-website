<?php

namespace App\Policies;

use App\Models\BankAdjustment;
use App\Models\User;
use App\Policies\Concerns\TreasuryBankScope;

class BankAdjustmentPolicy
{
    use TreasuryBankScope;

    public function view(User $user, BankAdjustment $adjustment): bool
    {
        return $user->hasPermission('treasury.bank_adjustments.view')
            && $this->bankScope($user, $adjustment->bankAccount);
    }

    public function update(User $user, BankAdjustment $adjustment): bool
    {
        return $adjustment->status === 'draft' && $user->hasPermission('treasury.bank_adjustments.update')
            && $this->view($user, $adjustment);
    }

    public function approve(User $user, BankAdjustment $adjustment): bool
    {
        return $adjustment->created_by !== $user->id
            && $user->hasPermission('treasury.bank_adjustments.approve') && $this->view($user, $adjustment);
    }

    public function post(User $user, BankAdjustment $adjustment): bool
    {
        return $adjustment->approved_by !== $user->id
            && $user->hasPermission('treasury.bank_adjustments.post') && $this->view($user, $adjustment);
    }
}
