<?php

namespace App\Policies;

use App\Models\RollScrap;
use App\Models\User;
use App\Policies\Concerns\ChecksInventoryScope;

class RollScrapPolicy
{
    use ChecksInventoryScope;

    public function view(User $user, RollScrap $scrap): bool
    {
        return $this->branch($user, $scrap) && $user->hasPermission('rolls.view');
    }

    public function manage(User $user, RollScrap $scrap): bool
    {
        return $this->branch($user, $scrap) && $user->hasPermission('rolls.manage_scraps');
    }
}
