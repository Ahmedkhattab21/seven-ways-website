<?php

namespace App\Policies;

use App\Models\ProductAccountingMapping;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class ProductAccountingMappingPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.mappings.products');
    }

    public function update(User $user, ProductAccountingMapping $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }
}
