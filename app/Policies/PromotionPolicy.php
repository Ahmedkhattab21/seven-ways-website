<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class PromotionPolicy
{
    use ChecksServiceScope;

    public function view(User $user, Promotion $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('promotions.view');
    }

    public function update(User $user, Promotion $model): bool
    {
        return $this->company($user, $model) && $user->hasPermission('promotions.manage');
    }
}
