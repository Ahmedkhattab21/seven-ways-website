<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait TreasuryOperationScope
{
    protected function operationScope(User $user, Model $model, string $permission): bool
    {
        if ((int) $model->company_id !== (int) $user->company_id || ! $user->hasPermission($permission)) {
            return false;
        }
        $branch = $model->branch ?? $model->session?->cashBox?->branch;

        return ! $branch || $user->canAccessBranch($branch);
    }
}
