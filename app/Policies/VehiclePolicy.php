<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->inScope($user, $vehicle) && $user->hasPermission('vehicles.view');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->inScope($user, $vehicle) && $user->hasPermission('vehicles.update');
    }

    public function transfer(User $user, Vehicle $vehicle): bool
    {
        return $this->inScope($user, $vehicle) && $user->hasPermission('vehicles.transfer_ownership');
    }

    private function inScope(User $user, Vehicle $vehicle): bool
    {
        return (int) $vehicle->company_id === (int) $user->company_id
            && ($user->isCompanyAdministrator() || ($vehicle->customer?->assignedBranch && $user->canAccessBranch($vehicle->customer->assignedBranch)));
    }
}
