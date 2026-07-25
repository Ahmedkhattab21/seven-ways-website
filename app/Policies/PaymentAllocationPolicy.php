<?php

namespace App\Policies;

use App\Models\PaymentAllocation;
use App\Models\User;

class PaymentAllocationPolicy
{
    public function reverse(User $user, PaymentAllocation $allocation): bool
    {
        return $user->company_id === $allocation->company_id
            && $user->canAccessBranch($allocation->invoice->branch)
            && $user->hasPermission('customer_payments.reverse_allocation');
    }
}
