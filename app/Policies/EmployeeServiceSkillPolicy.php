<?php

namespace App\Policies;

use App\Models\EmployeeServiceSkill;
use App\Models\User;
use App\Policies\Concerns\ChecksServiceScope;

class EmployeeServiceSkillPolicy
{
    use ChecksServiceScope;

    public function update(User $user, EmployeeServiceSkill $model): bool
    {
        return $this->branch($user, $model) && $user->hasPermission('services.manage_skills');
    }
}
