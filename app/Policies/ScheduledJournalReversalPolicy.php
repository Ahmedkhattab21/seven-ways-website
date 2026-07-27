<?php

namespace App\Policies;

use App\Models\ScheduledJournalReversal;
use App\Models\User;
use App\Policies\Concerns\ClosingPolicyScope;

class ScheduledJournalReversalPolicy
{
    use ClosingPolicyScope;

    public function view(User $user, ScheduledJournalReversal $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.adjustments.view');
    }
}
