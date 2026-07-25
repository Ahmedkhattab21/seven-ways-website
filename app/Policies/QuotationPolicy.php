<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    private function scoped(User $user, Quotation $quotation): bool
    {
        return $user->company_id === $quotation->company_id && $user->canAccessBranch($quotation->branch);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.view');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $quotation->status === 'draft' && $user->hasPermission('quotations.update');
    }

    public function submit(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $quotation->status === 'draft' && $user->hasPermission('quotations.submit');
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $quotation->status === 'pending_approval' && $user->hasPermission('quotations.approve');
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $quotation->status === 'approved' && $user->hasPermission('quotations.send');
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.accept');
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.reject');
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.cancel');
    }

    public function createVersion(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.create_version');
    }

    public function print(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.print');
    }

    public function viewCost(User $user, Quotation $quotation): bool
    {
        return $this->scoped($user, $quotation) && $user->hasPermission('quotations.view_cost');
    }
}
