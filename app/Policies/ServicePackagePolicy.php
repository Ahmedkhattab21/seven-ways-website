<?php

namespace App\Policies;

use App\Models\ServicePackage;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServicePackagePolicy
{
    use ChecksServiceScope;

    public function view(User $user, ServicePackage $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('service_packages.view');
    }

    public function update(User $user, ServicePackage $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('service_packages.update');
    }

    public function disable(User $user, ServicePackage $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('service_packages.disable');
    }
}
