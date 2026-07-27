<?php

namespace App\Policies;

use App\Models\BankReconciliationSession;
use App\Models\User;
use App\Policies\Concerns\TreasuryBankScope;

class BankReconciliationSessionPolicy
{
    use TreasuryBankScope;

    public function view(User $user, BankReconciliationSession $session): bool
    {
        return $user->hasPermission('treasury.reconciliation.view') && $this->bankScope($user, $session->bankAccount);
    }

    public function match(User $user, BankReconciliationSession $session): bool
    {
        return in_array($session->status, ['matching', 'ready_for_review', 'reopened'], true)
            && $user->hasPermission('treasury.reconciliation.match') && $this->view($user, $session);
    }

    public function review(User $user, BankReconciliationSession $session): bool
    {
        return $session->started_by !== $user->id && $user->hasPermission('treasury.reconciliation.review')
            && $this->view($user, $session);
    }

    public function approve(User $user, BankReconciliationSession $session): bool
    {
        return ! in_array($user->id, [$session->started_by, $session->reviewed_by], true)
            && $user->hasPermission('treasury.reconciliation.approve') && $this->view($user, $session);
    }

    public function complete(User $user, BankReconciliationSession $session): bool
    {
        return $user->hasPermission('treasury.reconciliation.complete') && $this->view($user, $session);
    }

    public function cancel(User $user, BankReconciliationSession $session): bool
    {
        return $session->status !== 'completed'
            && $user->hasPermission('treasury.reconciliation.complete') && $this->view($user, $session);
    }

    public function reopen(User $user, BankReconciliationSession $session): bool
    {
        return $session->completed_by !== $user->id && $user->hasPermission('treasury.reconciliation.reopen')
            && $this->view($user, $session);
    }
}
