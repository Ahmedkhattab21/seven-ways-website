<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class StockAdjustmentPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $this->branch($user, $adjustment) && $user->hasPermission('inventory.view');
    }

    public function post(User $user, StockAdjustment $adjustment): bool
    {
        return $this->branch($user, $adjustment) && $user->hasPermission('inventory.post');
    }
}
