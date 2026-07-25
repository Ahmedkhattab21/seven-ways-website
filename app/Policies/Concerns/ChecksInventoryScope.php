<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksInventoryScope
{
    private function company(User $user, Model $model): bool
    {
        return (int) $model->company_id === (int) $user->company_id;
    }

    private function branch(User $user, Model $model): bool
    {
        return $this->company($user, $model)
            && (! isset($model->branch_id) || $user->isCompanyAdministrator() || $user->canAccessBranch($model->branch));
    }
}
