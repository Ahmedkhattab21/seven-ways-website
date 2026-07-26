<?php

namespace App\Services;

class BalanceSheetService
{
    public function __construct(
        private FinancialStatementService $statements,
        private IncomeStatementService $income
    ) {
    }

    public function report(array $filters): array
    {
        $incomeFilters = $filters;
        $balanceFilters = [...$filters, 'date_from' => '1900-01-01'];
        $rows = $this->statements->balances($balanceFilters, ['asset', 'liability', 'equity'], true);
        $assets = $this->sum($rows->where('classification', 'asset'));
        $liabilities = $this->sum($rows->where('classification', 'liability'));
        $equity = $this->sum($rows->where('classification', 'equity'));
        $profit = $this->income->report($incomeFilters)['net_profit'];
        $liabilitiesAndEquity = bcadd(bcadd($liabilities, $equity, 4), $profit, 4);
        $difference = bcsub($assets, $liabilitiesAndEquity, 4);

        return [
            'rows' => $rows, 'assets' => $assets, 'liabilities' => $liabilities,
            'equity' => $equity, 'current_profit' => $profit,
            'liabilities_and_equity' => $liabilitiesAndEquity, 'difference' => $difference,
            'balanced' => bccomp($difference, '0', 4) === 0,
        ];
    }

    private function sum($rows): string
    {
        return $rows->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000');
    }
}
