<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderService;

class WorkOrderServicePolicy
{
    private function scoped(User $user, WorkOrderService $line): bool
    {
        return $user->company_id === $line->workOrder->company_id && $user->canAccessBranch($line->workOrder->branch);
    }

    private function assigned(User $user, WorkOrderService $line): bool
    {
        return ! $user->hasRole('technician') || $line->technicians()->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))->exists();
    }

    public function view(User $user, WorkOrderService $line): bool
    {
        return $this->scoped($user, $line) && $this->assigned($user, $line) && $user->hasPermission('work_orders.view');
    }

    public function act(User $user, WorkOrderService $line): bool
    {
        return $this->scoped($user, $line) && $this->assigned($user, $line) && $user->hasPermission('work_orders.start');
    }
}
