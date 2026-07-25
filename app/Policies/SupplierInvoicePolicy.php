<?php

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class SupplierInvoicePolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('supplier_invoices.view');
    }

    public function view(User $user, SupplierInvoice $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier_invoices.create');
    }

    public function submit(User $user, SupplierInvoice $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_invoices.submit');
    }

    public function approve(User $user, SupplierInvoice $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_invoices.approve');
    }

    public function post(User $user, SupplierInvoice $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_invoices.post');
    }

    public function viewCost(User $user, SupplierInvoice $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_invoices.view_cost');
    }
}
