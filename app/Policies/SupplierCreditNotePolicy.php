<?php

namespace App\Policies;

use App\Models\SupplierCreditNote;
use App\Models\User;
use App\Policies\Concerns\PurchasingPolicyScope;

class SupplierCreditNotePolicy
{
    use PurchasingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('supplier_credit_notes.view');
    }

    public function view(User $user, SupplierCreditNote $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_credit_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier_credit_notes.create');
    }

    public function approve(User $user, SupplierCreditNote $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_credit_notes.approve');
    }

    public function post(User $user, SupplierCreditNote $model): bool
    {
        return $this->purchasingScoped($user, $model) && $user->hasPermission('supplier_credit_notes.post');
    }
}
