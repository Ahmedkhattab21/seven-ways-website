<?php

namespace App\Policies;

use App\Models\User;

class FinancialStatementPolicy
{
    public function incomeStatement(User $user): bool
    {
        return $user->hasPermission('accounting.income_statement.view');
    }

    public function balanceSheet(User $user): bool
    {
        return $user->hasPermission('accounting.balance_sheet.view');
    }

    public function cashFlow(User $user): bool
    {
        return $user->hasPermission('accounting.cash_flow.view');
    }
}
