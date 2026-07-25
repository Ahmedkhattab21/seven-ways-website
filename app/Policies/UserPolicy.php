<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $user): bool
    {
        return $this->sharesManageableBranch($actor, $user) && $actor->hasPermission('users.view');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->id !== $user->id
            && $this->sharesManageableBranch($actor, $user)
            && $actor->hasPermission('users.update');
    }

    public function disable(User $actor, User $user): bool
    {
        return $actor->id !== $user->id
            && $this->sharesManageableBranch($actor, $user)
            && $actor->hasPermission('users.disable');
    }

    private function sharesManageableBranch(User $actor, User $user): bool
    {
        if ($actor->company_id !== $user->company_id) {
            return false;
        }

        if ($actor->isCompanyAdministrator()) {
            return true;
        }

        $branchIds = $actor->accessibleBranches()->wherePivot('can_view', true)->pluck('branches.id');
        if ($actor->branch_id) {
            $branchIds->push($actor->branch_id);
        }

        return ($user->branch_id && $branchIds->contains($user->branch_id))
            || $user->accessibleBranches()->whereIn('branches.id', $branchIds)->exists();
    }
}
