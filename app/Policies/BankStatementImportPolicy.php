<?php

namespace App\Policies;

use App\Models\BankStatementImport;
use App\Models\User;
use App\Policies\Concerns\TreasuryBankScope;

class BankStatementImportPolicy
{
    use TreasuryBankScope;

    public function view(User $user, BankStatementImport $import): bool
    {
        return $user->hasPermission('treasury.bank_statements.view') && $this->bankScope($user, $import->bankAccount);
    }

    public function cancel(User $user, BankStatementImport $import): bool
    {
        return ! in_array($import->status, ['cancelled'], true)
            && $user->hasPermission('treasury.bank_statements.cancel') && $this->bankScope($user, $import->bankAccount);
    }
}
