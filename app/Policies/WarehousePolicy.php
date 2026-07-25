<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use App\Policies\Concerns\ChecksInventoryScope;

class WarehousePolicy
{
    use ChecksInventoryScope;

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->branch($user, $warehouse) && $user->hasPermission('warehouses.view');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->branch($user, $warehouse) && $user->hasPermission('warehouses.update');
    }

    public function disable(User $user, Warehouse $warehouse): bool
    {
        return $this->branch($user, $warehouse) && $user->hasPermission('warehouses.disable');
    }
}
