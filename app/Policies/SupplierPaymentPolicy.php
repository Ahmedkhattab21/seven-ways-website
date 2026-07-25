<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class SupplierPaymentPolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('supplier_payments.view');
    }

    public function view(User $user, SupplierPayment $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier_payments.create');
    }

    public function approve(User $user, SupplierPayment $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_payments.approve');
    }

    public function process(User $user, SupplierPayment $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_payments.process');
    }

    public function allocate(User $user, SupplierPayment $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_payments.allocate');
    }
}
