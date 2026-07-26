<?php

namespace App\Policies;

use App\Models\PaymentMethodAccountMapping;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class PaymentMethodAccountMappingPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.mappings.payment_methods');
    }

    public function update(User $user, PaymentMethodAccountMapping $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }
}
