<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class StockMovementPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, StockMovement $movement): bool
    {
        return $this->branch($user, $movement) && $user->hasPermission('inventory.view');
    }

    public function reverse(User $user, StockMovement $movement): bool
    {
        return $this->branch($user, $movement) && $user->hasPermission('inventory.reverse');
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
