<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleInspection;

class VehicleInspectionPolicy
{
    private function scoped(User $user, VehicleInspection $inspection): bool
    {
        return $user->company_id === $inspection->company_id && $user->canAccessBranch($inspection->workOrder->branch);
    }

    public function view(User $user, VehicleInspection $inspection): bool
    {
        $permission = $inspection->inspection_type === 'delivery'
            ? 'vehicle_inspections.delivery'
            : 'vehicle_inspections.view';

        return $this->scoped($user, $inspection) && $user->hasPermission($permission);
    }

    public function update(User $user, VehicleInspection $inspection): bool
    {
        $permission = $inspection->inspection_type === 'delivery'
            ? 'vehicle_inspections.delivery'
            : 'vehicle_inspections.update';

        return $this->scoped($user, $inspection) && $inspection->status === 'draft' && $user->hasPermission($permission);
    }

    public function complete(User $user, VehicleInspection $inspection): bool
    {
        $permission = $inspection->inspection_type === 'delivery'
            ? 'vehicle_inspections.delivery'
            : 'vehicle_inspections.complete';

        return $this->scoped($user, $inspection) && $inspection->status === 'draft' && $user->hasPermission($permission);
    }
}
