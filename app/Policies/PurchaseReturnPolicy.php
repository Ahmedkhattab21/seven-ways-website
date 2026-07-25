<?php

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class PurchaseReturnPolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_returns.view');
    }

    public function view(User $user, PurchaseReturn $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_returns.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_returns.create');
    }

    public function submit(User $user, PurchaseReturn $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_returns.create');
    }

    public function approve(User $user, PurchaseReturn $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_returns.approve');
    }

    public function post(User $user, PurchaseReturn $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_returns.post');
    }
}
