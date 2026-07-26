<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeStatementRequest;
use App\Services\ComparativeFinancialReportService;
use App\Services\FinancialReportViewDataService;
use App\Services\IncomeStatementService;

class IncomeStatementController extends Controller
{
    public function __invoke(
        IncomeStatementRequest $request,
        IncomeStatementService $service,
        ComparativeFinancialReportService $comparatives,
        FinancialReportViewDataService $viewData
    ) {
        abort_unless($request->user()->hasPermission('accounting.income_statement.view'), 403);
        $filters = $request->validated();
        $report = $service->report($filters);
        $comparison = null;
        if (! empty($filters['comparison']) && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $previous = $service->report($comparatives->previousFilters($filters, $filters['comparison']));
            $comparison = $comparatives->compare($report, $previous, [
                'revenue', 'cost_of_sales', 'gross_profit', 'operating_expenses', 'net_profit',
            ]);
        }

        return view('accounting.reports.income-statement', $report + compact('comparison') + $viewData->filters());
    }
}
