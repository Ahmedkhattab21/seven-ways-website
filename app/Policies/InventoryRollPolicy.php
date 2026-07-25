<?php

namespace App\Policies;

use App\Models\InventoryRoll;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class InventoryRollPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, InventoryRoll $roll): bool
    {
        return $this->branch($user, $roll) && $user->hasPermission('rolls.view');
    }

    public function consume(User $user, InventoryRoll $roll): bool
    {
        return $this->branch($user, $roll) && $user->hasPermission('rolls.consume');
    }
}
