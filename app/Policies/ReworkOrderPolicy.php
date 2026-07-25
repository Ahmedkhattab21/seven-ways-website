<?php

namespace App\Policies;

use App\Models\ReworkOrder;
use App\Models\User;

class ReworkOrderPolicy
{
    private function scoped(User $user, ReworkOrder $rework): bool
    {
        return (int) $user->company_id === (int) $rework->company_id
            && $user->canAccessBranch($rework->workOrder->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rework_orders.view');
    }

    public function view(User $user, ReworkOrder $rework): bool
    {
        return $this->scoped($user, $rework) && $user->hasPermission('rework_orders.view');
    }

    public function approve(User $user, ReworkOrder $rework): bool
    {
        return $this->scoped($user, $rework) && $user->hasPermission('rework_orders.approve');
    }

    public function start(User $user, ReworkOrder $rework): bool
    {
        return $this->scoped($user, $rework) && $user->hasPermission('rework_orders.start');
    }

    public function complete(User $user, ReworkOrder $rework): bool
    {
        return $this->scoped($user, $rework) && $user->hasPermission('rework_orders.complete');
    }

    public function viewCost(User $user, ReworkOrder $rework): bool
    {
        return $this->scoped($user, $rework) && $user->hasPermission('rework_orders.view_cost');
    }
}
