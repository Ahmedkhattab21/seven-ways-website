<?php

namespace App\Policies;

use App\Models\CustomerRefund;
use App\Models\User;

class CustomerRefundPolicy
{
    private function scoped(User $user, CustomerRefund $refund): bool
    {
        return $user->company_id === $refund->company_id && $user->canAccessBranch($refund->creditNote->invoice->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer_refunds.view');
    }

    public function view(User $user, CustomerRefund $refund): bool
    {
        return $this->scoped($user, $refund) && $user->hasPermission('customer_refunds.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_refunds.create');
    }

    public function approve(User $user, CustomerRefund $refund): bool
    {
        return $this->scoped($user, $refund) && $user->hasPermission('customer_refunds.approve');
    }

    public function process(User $user, CustomerRefund $refund): bool
    {
        return $this->scoped($user, $refund) && $user->hasPermission('customer_refunds.process');
    }
}
