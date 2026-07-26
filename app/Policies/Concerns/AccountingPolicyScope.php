<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AccountingPolicyScope
{
    private function accountingScoped(User $user, object $model): bool
    {
        return $user->company_id === $model->company_id;
    }
}
