<?php

namespace App\Policies;

use App\Models\InventoryCount;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class InventoryCountPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, InventoryCount $count): bool
    {
        return $this->branch($user, $count) && $user->hasPermission('inventory.view');
    }

    public function post(User $user, InventoryCount $count): bool
    {
        return $this->branch($user, $count) && $user->hasPermission('inventory.post');
    }
}
