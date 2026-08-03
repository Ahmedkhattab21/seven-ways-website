<?php

namespace App\Services;

use App\Analytics\ReportFilterData;

class ExecutiveDashboardService
{
    public function __construct(
        private AnalyticsReportService $reports,
        private OperationalDashboardService $operations
    ) {
    }

    public function build(ReportFilterData $filters): array
    {
        $current = $this->snapshots($filters);
        $previous = $this->snapshots($filters->previousPeriod());
        $comparisons = [];
        foreach ([
            'net_sales' => ['sales', 'net_sales_before_tax'],
            'operating_result' => ['financial', 'estimated_operating_result'],
            'receivables' => ['receivables', 'outstanding'],
            'payables' => ['payables', 'outstanding'],
            'inventory' => ['inventory', 'stock_valuation'],
            'cash_and_bank' => ['treasury', 'total_cash_and_bank'],
        ] as $key => [$group, $metric]) {
            $comparisons[$key] = $this->change(
                $current[$group][$metric] ?? 0,
                $previous[$group][$metric] ?? 0
            );
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'comparisons' => $comparisons,
            'operational' => $this->operations->summary($filters),
            'branch_comparison' => $this->operations->byBranch($filters),
            'period' => [$filters->dateFrom, $filters->dateTo],
            'previous_period' => [$filters->previousPeriod()->dateFrom, $filters->previousPeriod()->dateTo],
        ];
    }

    private function snapshots(ReportFilterData $filters): array
    {
        $sales = $this->reports->run('sales', $filters, 12);
        $financial = $this->reports->run('financial', $filters, 12);
        $receivables = $this->reports->run('receivables', $filters, 12);
        $payables = $this->reports->run('payables', $filters, 12);
        $inventory = $this->reports->run('inventory', $filters, 12);
        $treasury = $this->reports->run('treasury', $filters, 12);
        $employees = $this->reports->run('employee-finance', $filters, 12);
        $approvals = $this->reports->run('approvals', $filters, 12);
        $treasurySummary = $treasury->summary + ['total_cash_and_bank' => bcadd(
            $treasury->summary['cash_book_balance'],
            $treasury->summary['bank_book_balance'],
            4
        )];

        return [
            'sales' => $sales->summary,
            'financial' => $financial->summary,
            'receivables' => $receivables->summary,
            'payables' => $payables->summary,
            'inventory' => $inventory->summary,
            'treasury' => $treasurySummary,
            'employees' => $employees->summary,
            'approvals' => $approvals->summary,
            'sales_trend' => $sales->meta['sales_trend'],
        ];
    }

    private function change(mixed $current, mixed $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        return [
            'difference' => $current - $previous,
            'percentage' => abs($previous) < 0.00001
                ? null
                : round((($current - $previous) / abs($previous)) * 100, 2),
        ];
    }
}
