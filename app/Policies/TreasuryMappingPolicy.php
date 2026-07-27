<?php

namespace App\Policies;

use App\Models\PaymentMethodAccountMapping;
use App\Models\User;

class TreasuryMappingPolicy
{
    public function view(User $user, PaymentMethodAccountMapping $mapping): bool
    {
        return $mapping->company_id === $user->company_id && $user->hasPermission('treasury.mappings.view');
    }

    public function update(User $user, PaymentMethodAccountMapping $mapping): bool
    {
        return $mapping->company_id === $user->company_id && $user->hasPermission('treasury.mappings.update');
    }
}
