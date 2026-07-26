<?php

namespace App\Policies;

use App\Models\PostingProfile;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class PostingProfilePolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.posting_profiles.view');
    }

    public function view(User $user, PostingProfile $model): bool
    {
        return $this->accountingScoped($user, $model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.posting_profiles.create');
    }

    public function update(User $user, PostingProfile $model): bool
    {
        return $this->accountingScoped($user, $model) && $model->status === 'draft' && $user->hasPermission('accounting.posting_profiles.update');
    }

    public function activate(User $user, PostingProfile $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.posting_profiles.activate');
    }

    public function supersede(User $user, PostingProfile $model): bool
    {
        return $this->accountingScoped($user, $model) && $user->hasPermission('accounting.posting_profiles.supersede');
    }
}
