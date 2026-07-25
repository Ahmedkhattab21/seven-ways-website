<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    private function scoped(User $user, WorkOrder $order): bool
    {
        return $user->company_id === $order->company_id && $user->canAccessBranch($order->branch);
    }

    private function assigned(User $user, WorkOrder $order): bool
    {
        return ! $user->hasRole('technician') || $order->services()->whereHas('technicians.employee', fn ($query) => $query->where('user_id', $user->id))->exists();
    }

    public function view(User $user, WorkOrder $order): bool
    {
        return $this->scoped($user, $order) && $this->assigned($user, $order) && $user->hasPermission('work_orders.view');
    }

    public function update(User $user, WorkOrder $order): bool
    {
        return $this->scoped($user, $order) && ! in_array($order->status, WorkOrder::TERMINAL_STATUSES, true) && $user->hasPermission('work_orders.update');
    }

    public function cancel(User $user, WorkOrder $order): bool
    {
        return $this->scoped($user, $order) && $user->hasPermission('work_orders.cancel');
    }

    public function viewCost(User $user, WorkOrder $order): bool
    {
        return $this->scoped($user, $order) && $user->hasPermission('work_orders.view_cost');
    }
}
