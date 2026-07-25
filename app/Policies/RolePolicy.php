<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function view(User $user, Role $role): bool
    {
        return ($role->company_id === null || $role->company_id === $user->company_id)
            && $user->hasPermission('roles.view');
    }

    public function update(User $user, Role $role): bool
    {
        return ! $role->is_system
            && $role->company_id === $user->company_id
            && $user->hasPermission('roles.manage');
    }
}
