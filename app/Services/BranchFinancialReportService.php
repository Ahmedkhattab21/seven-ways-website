<?php

namespace App\Services;

class BranchFinancialReportService
{
    public function __construct(
        private TrialBalanceService $trialBalance,
        private IncomeStatementService $incomeStatement,
        private BalanceSheetService $balanceSheet
    ) {
    }

    public function report(array $filters): array
    {
        return [
            'trial_balance' => $this->trialBalance->report($filters),
            'income_statement' => $this->incomeStatement->report($filters),
            'balance_sheet' => $this->balanceSheet->report($filters),
        ];
    }
}
