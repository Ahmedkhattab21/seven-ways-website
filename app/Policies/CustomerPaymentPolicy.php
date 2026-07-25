<?php

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    private function scoped(User $user, CustomerPayment $payment): bool
    {
        return $user->company_id === $payment->company_id && $user->canAccessBranch($payment->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer_payments.view');
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $this->scoped($user, $payment) && $user->hasPermission('customer_payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_payments.record');
    }

    public function approve(User $user, CustomerPayment $payment): bool
    {
        return $this->scoped($user, $payment) && $user->hasPermission('customer_payments.approve');
    }
}
