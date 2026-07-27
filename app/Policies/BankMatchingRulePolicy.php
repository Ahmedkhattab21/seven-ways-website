<?php

namespace App\Policies;

use App\Models\BankMatchingRule;
use App\Models\User;

class BankMatchingRulePolicy
{
    public function view(User $user, BankMatchingRule $rule): bool
    {
        return $rule->company_id === $user->company_id && $user->hasPermission('treasury.matching_rules.view');
    }

    public function update(User $user, BankMatchingRule $rule): bool
    {
        return $rule->company_id === $user->company_id && $user->hasPermission('treasury.matching_rules.update');
    }
}
