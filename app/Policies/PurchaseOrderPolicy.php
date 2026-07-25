<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class PurchaseOrderPolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('purchase_orders.update');
    }

    public function submit(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_orders.submit');
    }

    public function approve(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_orders.approve');
    }

    public function send(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_orders.send');
    }

    public function cancel(User $user, PurchaseOrder $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_orders.cancel');
    }
}
