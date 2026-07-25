<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksServiceScope
{
    protected function company(User $user, object $model): bool
    {
        $companyId = $model->company_id ?? $model->service?->company_id
            ?? $model->package?->company_id ?? $model->requirement?->company_id;

        return (int) $companyId === (int) $user->company_id;
    }

    protected function branch(User $user, object $model): bool
    {
        return $this->company($user, $model) && $model->branch && $user->canAccessBranch($model->branch);
    }
}
