<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServiceCategoryPolicy
{
    use ChecksServiceScope;

    public function view(User $user, ServiceCategory $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('service_categories.view');
    }

    public function update(User $user, ServiceCategory $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('service_categories.manage');
    }

    public function disable(User $user, ServiceCategory $model): bool
    {
        return $this->update($user, $model);
    }
}
