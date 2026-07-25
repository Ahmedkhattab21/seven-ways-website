<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServicePolicy
{
    use ChecksServiceScope;

    public function view(User $user, Service $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.view');
    }

    public function update(User $user, Service $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.update');
    }

    public function disable(User $user, Service $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.disable');
    }

    public function viewCost(User $user, Service $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('services.view_cost');
    }
}
