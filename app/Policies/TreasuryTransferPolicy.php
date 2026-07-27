<?php

namespace App\Policies;

use App\Models\TreasuryTransfer;
use App\Models\User;

class TreasuryTransferPolicy
{
    public function view(User $user, TreasuryTransfer $transfer): bool
    {
        return $transfer->company_id === $user->company_id && $user->canAccessBranch($transfer->branch)
            && $user->hasPermission('treasury.transfers.view');
    }

    public function update(User $user, TreasuryTransfer $transfer): bool
    {
        return $transfer->company_id === $user->company_id && $transfer->status === 'draft'
            && $user->canAccessBranch($transfer->branch) && $user->hasPermission('treasury.transfers.update');
    }

    public function approve(User $user, TreasuryTransfer $transfer): bool
    {
        return $transfer->company_id === $user->company_id && $transfer->created_by !== $user->id
            && $user->hasPermission('treasury.transfers.approve');
    }

    public function process(User $user, TreasuryTransfer $transfer): bool
    {
        return $transfer->company_id === $user->company_id
            && in_array($transfer->status, ['approved', 'failed'], true)
            && $user->canAccessBranch($transfer->branch)
            && $user->hasPermission('treasury.transfers.process');
    }

    public function reverse(User $user, TreasuryTransfer $transfer): bool
    {
        return $transfer->company_id === $user->company_id && $transfer->status === 'completed'
            && $user->canAccessBranch($transfer->branch)
            && $user->hasPermission('treasury.transfers.reverse');
    }
}
