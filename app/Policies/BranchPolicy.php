<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return ($user->canAccessBranch($branch)
            || ($user->isCompanyAdministrator() && $branch->company_id === $user->company_id))
            && $user->hasPermission('branches.view');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch) && $user->hasPermission('branches.update');
    }

    public function disable(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch) && $user->hasPermission('branches.disable');
    }
}
