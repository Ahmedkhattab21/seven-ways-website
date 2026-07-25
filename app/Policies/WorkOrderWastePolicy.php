<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderWasteRecord;

class WorkOrderWastePolicy
{
    public function view(User $user, WorkOrderWasteRecord $waste): bool
    {
        return $user->company_id === $waste->workOrder->company_id && $user->canAccessBranch($waste->workOrder->branch) && $user->hasPermission('work_order_materials.view');
    }
}
