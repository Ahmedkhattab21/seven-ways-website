<?php

namespace App\Policies;

use App\Models\SalesCreditNote;
use App\Models\User;

class SalesCreditNotePolicy
{
    private function scoped(User $user, SalesCreditNote $note): bool
    {
        return $user->company_id === $note->company_id && $user->canAccessBranch($note->invoice->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_credit_notes.view');
    }

    public function view(User $user, SalesCreditNote $note): bool
    {
        return $this->scoped($user, $note) && $user->hasPermission('sales_credit_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_credit_notes.create');
    }

    public function approve(User $user, SalesCreditNote $note): bool
    {
        return $this->scoped($user, $note) && $user->hasPermission('sales_credit_notes.approve');
    }

    public function issue(User $user, SalesCreditNote $note): bool
    {
        return $this->scoped($user, $note) && $user->hasPermission('sales_credit_notes.issue');
    }

    public function print(User $user, SalesCreditNote $note): bool
    {
        return $this->scoped($user, $note) && $user->hasPermission('sales_credit_notes.print');
    }
}
