<?php

namespace App\Policies;

use App\Models\CashBoxCount;
use App\Models\User;
use App\Policies\Concerns\TreasuryOperationScope;

class CashBoxCountPolicy
{
    use TreasuryOperationScope;

    public function view(User $user, CashBoxCount $count): bool
    {
        return $this->operationScope($user, $count, 'treasury.cash_sessions.view');
    }

    public function approve(User $user, CashBoxCount $count): bool
    {
        return $this->operationScope($user, $count, 'treasury.cash_sessions.approve')
            && $count->counted_by !== $user->id;
    }

    public function review(User $user, CashBoxCount $count): bool
    {
        return $this->operationScope($user, $count, 'treasury.cash_sessions.review')
            && $count->status === 'submitted'
            && $count->counted_by !== $user->id;
    }
}
