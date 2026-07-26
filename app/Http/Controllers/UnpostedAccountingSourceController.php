<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralLedgerReportRequest;
use App\Services\FinancialReportViewDataService;
use App\Services\UnpostedAccountingSourcesService;

class UnpostedAccountingSourceController extends Controller
{
    public function __invoke(GeneralLedgerReportRequest $request, UnpostedAccountingSourcesService $service, FinancialReportViewDataService $viewData)
    {
        abort_unless($request->user()->hasPermission('accounting.unposted_sources.view'), 403);

        return view('accounting.reports.unposted-sources', ['sources' => $service->report($request->validated())] + $viewData->filters());
    }
}
