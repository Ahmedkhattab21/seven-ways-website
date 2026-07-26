<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchFinancialReportRequest;
use App\Services\BranchFinancialReportService;
use App\Services\FinancialReportViewDataService;

class BranchFinancialReportController extends Controller
{
    public function __invoke(BranchFinancialReportRequest $request, BranchFinancialReportService $service, FinancialReportViewDataService $viewData)
    {
        abort_unless($request->user()->hasPermission('accounting.branch_reports.view'), 403);

        return view('accounting.reports.branches', $service->report($request->validated()) + $viewData->filters());
    }
}
