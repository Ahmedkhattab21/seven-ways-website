<?php

namespace App\Policies;

use App\Models\InventoryReservation;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class InventoryReservationPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, InventoryReservation $reservation): bool
    {
        return $this->branch($user, $reservation) && $user->hasPermission('inventory_reservations.view');
    }

    public function manage(User $user, InventoryReservation $reservation): bool
    {
        return $this->branch($user, $reservation) && $user->hasPermission('inventory_reservations.manage');
    }
}
