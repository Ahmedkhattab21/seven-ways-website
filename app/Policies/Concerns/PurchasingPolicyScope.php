<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait PurchasingPolicyScope
{
    private function purchasingScoped(User $user, object $model): bool
    {
        return $user->company_id === $model->company_id
            && (! isset($model->branch_id) || $user->canAccessBranch($model->branch));
    }
}
