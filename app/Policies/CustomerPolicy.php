<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $this->inScope($user, $customer) && $user->hasPermission('customers.view');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->inScope($user, $customer) && $user->hasPermission('customers.update');
    }

    public function disable(User $user, Customer $customer): bool
    {
        return $this->inScope($user, $customer) && $user->hasPermission('customers.disable');
    }

    private function inScope(User $user, Customer $customer): bool
    {
        return (int) $customer->company_id === (int) $user->company_id
            && ($user->isCompanyAdministrator() || ($customer->assignedBranch && $user->canAccessBranch($customer->assignedBranch)));
    }
}
