<?php

namespace App\Policies;

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class PurchaseRequisitionPolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_requisitions.view');
    }

    public function view(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_requisitions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_requisitions.create');
    }

    public function update(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('purchase_requisitions.update');
    }

    public function submit(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_requisitions.submit');
    }

    public function approve(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_requisitions.approve');
    }

    public function reject(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_requisitions.reject');
    }

    public function cancel(User $user, PurchaseRequisition $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('purchase_requisitions.cancel');
    }
}
