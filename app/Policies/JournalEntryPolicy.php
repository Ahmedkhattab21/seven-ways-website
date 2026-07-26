<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Policies\Concerns\AccountingPolicyScope;

class JournalEntryPolicy
{
    use AccountingPolicyScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.journals.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.journals.create');
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $entry->status === 'draft' && ! $entry->is_automatic && $user->hasPermission('accounting.journals.update');
    }

    public function submit(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $user->hasPermission('accounting.journals.submit');
    }

    public function approve(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $user->hasPermission('accounting.journals.approve');
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $user->hasPermission('accounting.journals.post');
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $user->hasPermission('accounting.journals.reverse');
    }

    public function cancel(User $user, JournalEntry $entry): bool
    {
        return $this->accountingScoped($user, $entry) && $user->hasPermission('accounting.journals.cancel');
    }
}
