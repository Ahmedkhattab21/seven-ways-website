<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarrantyClaim;

class WarrantyClaimPolicy
{
    private function scoped(User $user, WarrantyClaim $claim): bool
    {
        return (int) $user->company_id === (int) $claim->company_id
            && $user->canAccessBranch($claim->warranty->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('warranty_claims.view');
    }

    public function view(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('warranty_claims.create');
    }

    public function inspect(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.inspect');
    }

    public function decide(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.decide');
    }

    public function approve(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.approve');
    }

    public function resolve(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.resolve');
    }

    public function viewCost(User $user, WarrantyClaim $claim): bool
    {
        return $this->scoped($user, $claim) && $user->hasPermission('warranty_claims.view_cost');
    }
}
