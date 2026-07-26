<?php

namespace App\Services;

class CostCenterReportService
{
    public function __construct(
        private TrialBalanceService $trialBalance,
        private IncomeStatementService $incomeStatement,
        private FinancialReportQueryService $queries
    ) {
    }

    public function report(array $filters): array
    {
        $normalized = $this->queries->normalize($filters);
        $missing = $this->queries->postedLines($normalized)
            ->join('accounts as required_accounts', 'required_accounts.id', '=', 'jel.account_id')
            ->where('required_accounts.requires_cost_center', true)->whereNull('jel.cost_center_id')->count();

        return [
            'trial_balance' => $this->trialBalance->report($normalized),
            'income_statement' => $this->incomeStatement->report($normalized),
            'unassigned_required_dimensions' => $missing,
        ];
    }
}
