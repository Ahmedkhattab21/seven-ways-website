<?php

namespace App\Policies;

use App\Models\ServiceCommissionRule;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServiceCommissionRulePolicy
{
    use ChecksServiceScope;

    public function update(User $user, ServiceCommissionRule $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.manage_commissions');
    }
}
