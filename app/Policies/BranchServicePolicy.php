<?php

namespace App\Policies;

use App\Models\BranchService;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class BranchServicePolicy
{
    use ChecksServiceScope;

    public function update(User $user, BranchService $model): bool
    {
        return $this->branch($user, $model) && $user->hasPermission('services.manage_branch_availability');
    }
}
