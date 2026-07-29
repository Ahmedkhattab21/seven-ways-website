<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function view(User $user, Employee $employee): bool
    {
        return $this->inScope($user, $employee) && $user->hasPermission('employees.view');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->inScope($user, $employee) && $user->hasPermission('employees.update');
    }

    public function disable(User $user, Employee $employee): bool
    {
        return $this->inScope($user, $employee) && $user->hasPermission('employees.disable');
    }

    private function inScope(User $user, Employee $employee): bool
    {
        return (int) $employee->company_id === (int) $user->company_id
            && $employee->branch
            && $user->canAccessBranch($employee->branch);
    }
}
