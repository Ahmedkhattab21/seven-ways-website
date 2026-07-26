<?php

namespace App\Http\Controllers;

use App\Http\Requests\CostCenterReportRequest;
use App\Services\CostCenterReportService;
use App\Services\FinancialReportViewDataService;

class CostCenterFinancialReportController extends Controller
{
    public function __invoke(CostCenterReportRequest $request, CostCenterReportService $service, FinancialReportViewDataService $viewData)
    {
        abort_unless($request->user()->hasPermission('accounting.cost_center_reports.view'), 403);

        return view('accounting.reports.cost-centers', $service->report($request->validated()) + $viewData->filters());
    }
}
