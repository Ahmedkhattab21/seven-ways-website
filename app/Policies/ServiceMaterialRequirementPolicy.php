<?php

namespace App\Policies;

use App\Models\ServiceMaterialRequirement;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServiceMaterialRequirementPolicy
{
    use ChecksServiceScope;

    public function update(User $user, ServiceMaterialRequirement $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.manage_materials');
    }
}
