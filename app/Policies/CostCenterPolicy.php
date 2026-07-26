<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class CostCenterPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.cost_centers.view');
    }

    public function view(User $user, CostCenter $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.cost_centers.create');
    }

    public function update(User $user, CostCenter $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.cost_centers.update');
    }

    public function disable(User $user, CostCenter $model): bool
    {
        return $this->accountingScoped($user, $model) && ! $model->is_system && $user->hasPermission('accounting.cost_centers.disable');
    }

    public function move(User $user, CostCenter $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.cost_centers.move');
    }
}
