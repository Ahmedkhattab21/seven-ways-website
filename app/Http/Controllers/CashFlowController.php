<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashFlowRequest;
use App\Services\CashFlowStatementService;
use App\Services\FinancialReportViewDataService;

class CashFlowController extends Controller
{
    public function __invoke(CashFlowRequest $request, CashFlowStatementService $service, FinancialReportViewDataService $viewData)
    {
        abort_unless($request->user()->hasPermission('accounting.cash_flow.view'), 403);

        return view('accounting.reports.cash-flow', $service->report($request->validated()) + $viewData->filters());
    }
}
