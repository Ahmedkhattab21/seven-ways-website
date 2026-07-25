<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderMaterial;

class WorkOrderMaterialPolicy
{
    private function scoped(User $user, WorkOrderMaterial $line): bool
    {
        return $user->company_id === $line->workOrder->company_id && $user->canAccessBranch($line->workOrder->branch);
    }

    public function view(User $user, WorkOrderMaterial $line): bool
    {
        return $this->scoped($user, $line) && $user->hasPermission('work_order_materials.view');
    }

    public function manage(User $user, WorkOrderMaterial $line): bool
    {
        return $this->scoped($user, $line) && $user->hasPermission('work_order_materials.issue');
    }
}
