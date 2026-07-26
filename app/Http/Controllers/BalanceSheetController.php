<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceSheetRequest;
use App\Services\BalanceSheetService;
use App\Services\ComparativeFinancialReportService;
use App\Services\FinancialReportViewDataService;

class BalanceSheetController extends Controller
{
    public function __invoke(
        BalanceSheetRequest $request,
        BalanceSheetService $service,
        ComparativeFinancialReportService $comparatives,
        FinancialReportViewDataService $viewData
    ) {
        abort_unless($request->user()->hasPermission('accounting.balance_sheet.view'), 403);
        $filters = $request->validated();
        if ($request->filled('as_of_date')) {
            $filters['date_to'] = $request->input('as_of_date');
        }
        $report = $service->report($filters);
        $comparison = null;
        if (! empty($filters['comparison']) && ! empty($filters['date_to'])) {
            $previousFilters = $comparatives->previousFilters(
                [...$filters, 'date_from' => $filters['date_from'] ?? $filters['date_to']],
                $filters['comparison']
            );
            $previous = $service->report($previousFilters);
            $comparison = $comparatives->compare($report, $previous, [
                'assets', 'liabilities', 'equity', 'current_profit', 'liabilities_and_equity',
            ]);
        }

        return view('accounting.reports.balance-sheet', $report + compact('comparison') + $viewData->filters());
    }
}
