<?php

namespace App\Policies;

use App\Models\BankStatementLine;
use App\Models\User;
use App\Policies\Concerns\TreasuryBankScope;

class BankStatementLinePolicy
{
    use TreasuryBankScope;

    public function view(User $user, BankStatementLine $line): bool
    {
        return $user->hasPermission('treasury.bank_statements.view') && $this->bankScope($user, $line->bankAccount);
    }

    public function ignore(User $user, BankStatementLine $line): bool
    {
        return $user->hasPermission('treasury.bank_statements.ignore_lines') && $this->view($user, $line);
    }

    public function resolveDuplicate(User $user, BankStatementLine $line): bool
    {
        return $user->hasPermission('treasury.bank_statements.resolve_duplicates') && $this->view($user, $line);
    }
}
