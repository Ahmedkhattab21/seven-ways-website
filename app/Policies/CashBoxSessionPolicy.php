<?php

namespace App\Policies;

use App\Models\CashBoxSession;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class CashBoxSessionPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, CashBoxSession $session): bool
    {
        return $this->operationScope($user, $session, 'treasury.cash_sessions.view');
    }

    public function count(User $user, CashBoxSession $session): bool
    {
        return $this->operationScope($user, $session, 'treasury.cash_sessions.count')
            && in_array($session->status, ['opened', 'counting'], true);
    }
}
