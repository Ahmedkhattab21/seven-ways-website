<?php

namespace App\Services;

class IncomeStatementService
{
    public function __construct(private FinancialStatementService $statements)
    {
    }

    public function report(array $filters): array
    {
        $rows = $this->statements->balances($filters, ['revenue', 'expense']);
        $revenue = $this->sum($rows->where('classification', 'revenue'));
        $cogs = $this->sum($rows->filter(fn ($row) => $row->classification === 'expense' && str_starts_with((string) $row->group_code, '5')));
        $operatingExpenses = $this->sum($rows->filter(fn ($row) => $row->classification === 'expense' && ! str_starts_with((string) $row->group_code, '5')));
        $grossProfit = bcsub($revenue, $cogs, 4);
        $operatingProfit = bcsub($grossProfit, $operatingExpenses, 4);

        return [
            'rows' => $rows, 'revenue' => $revenue, 'cost_of_sales' => $cogs,
            'gross_profit' => $grossProfit, 'operating_expenses' => $operatingExpenses,
            'operating_profit' => $operatingProfit, 'net_profit' => $operatingProfit,
            'gross_margin' => $this->percentage($grossProfit, $revenue),
            'net_margin' => $this->percentage($operatingProfit, $revenue),
        ];
    }

    private function sum($rows): string
    {
        return $rows->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->balance, 4), '0.0000');
    }

    private function percentage(string $amount, string $base): ?string
    {
        return bccomp($base, '0', 4) === 0 ? null : bcmul(bcdiv($amount, $base, 8), '100', 2);
    }
}
