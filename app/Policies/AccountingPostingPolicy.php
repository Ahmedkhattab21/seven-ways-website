<?php

namespace App\Policies;

use App\Models\User;

class AccountingPostingPolicy
{
    public function preview(User $user): bool
    {
        return $user->hasPermission('accounting.posting.preview');
    }

    public function execute(User $user): bool
    {
        return $user->hasPermission('accounting.posting.execute');
    }

    public function retry(User $user): bool
    {
        return $user->hasPermission('accounting.posting.retry');
    }

    public function reverse(User $user): bool
    {
        return $user->hasPermission('accounting.posting.reverse');
    }
}
