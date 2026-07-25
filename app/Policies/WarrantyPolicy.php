<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warranty;

class WarrantyPolicy
{
    private function scoped(User $user, Warranty $warranty): bool
    {
        return (int) $user->company_id === (int) $warranty->company_id
            && $user->canAccessBranch($warranty->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('warranties.view');
    }

    public function view(User $user, Warranty $warranty): bool
    {
        return $this->scoped($user, $warranty) && $user->hasPermission('warranties.view');
    }

    public function issue(User $user): bool
    {
        return $user->hasPermission('warranties.issue');
    }

    public function print(User $user, Warranty $warranty): bool
    {
        return $this->scoped($user, $warranty) && $user->hasPermission('warranties.print');
    }

    public function void(User $user, Warranty $warranty): bool
    {
        return $this->scoped($user, $warranty) && $user->hasPermission('warranties.void');
    }
}
