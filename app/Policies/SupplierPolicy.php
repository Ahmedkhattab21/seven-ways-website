<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->company_id === $supplier->company_id && $user->hasPermission('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->company_id === $supplier->company_id && $user->hasPermission('suppliers.update');
    }

    public function disable(User $user, Supplier $supplier): bool
    {
        return $user->company_id === $supplier->company_id && $user->hasPermission('suppliers.disable');
    }

    public function viewBalance(User $user, Supplier $supplier): bool
    {
        return $user->company_id === $supplier->company_id && $user->hasPermission('suppliers.view_balance');
    }
}
