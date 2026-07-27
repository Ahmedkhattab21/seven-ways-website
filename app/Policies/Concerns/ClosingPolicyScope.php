<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ClosingPolicyScope
{
    protected function tenant(User $user, Model $model): bool
    {
        return $user->company_id === $model->company_id;
    }
}
