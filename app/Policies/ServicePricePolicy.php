<?php

namespace App\Policies;

use App\Models\ServicePrice;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class ServicePricePolicy
{
    use ChecksServiceScope;

    public function view(User $user, ServicePrice $model): bool
    {
        return $this->branch($user, $model) && $user->hasPermission('services.view');
    }

    public function update(User $user, ServicePrice $model): bool
    {
        return $this->branch($user, $model) && $user->hasPermission('services.manage_prices');
    }
}
