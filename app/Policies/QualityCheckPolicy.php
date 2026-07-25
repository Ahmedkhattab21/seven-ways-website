<?php

namespace App\Policies;

use App\Models\QualityCheck;
use App\Models\User;
use App\Models\WorkOrder;

class QualityCheckPolicy
{
    private function scoped(User $user, QualityCheck $check): bool
    {
        return (int) $user->company_id === (int) $check->company_id
            && $user->canAccessBranch($check->workOrder->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('quality_checks.view');
    }

    public function view(User $user, QualityCheck $check): bool
    {
        return $this->scoped($user, $check) && $user->hasPermission('quality_checks.view');
    }

    public function create(User $user, WorkOrder $workOrder): bool
    {
        return (int) $user->company_id === (int) $workOrder->company_id
            && $user->canAccessBranch($workOrder->branch)
            && $user->hasPermission('quality_checks.create');
    }

    public function perform(User $user, QualityCheck $check): bool
    {
        return $this->scoped($user, $check) && $user->hasPermission('quality_checks.perform');
    }

    public function pass(User $user, QualityCheck $check): bool
    {
        return $this->scoped($user, $check) && $user->hasPermission('quality_checks.pass');
    }

    public function fail(User $user, QualityCheck $check): bool
    {
        return $this->scoped($user, $check) && $user->hasPermission('quality_checks.fail');
    }
}
