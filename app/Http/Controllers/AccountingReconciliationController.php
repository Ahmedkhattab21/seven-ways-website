<?php

namespace App\Http\Controllers;

use App\Http\Requests\ControlAccountReconciliationRequest;
use App\Services\ControlAccountReconciliationService;
use App\Services\FinancialReportViewDataService;

class AccountingReconciliationController extends Controller
{
    public function __invoke(ControlAccountReconciliationRequest $request, ControlAccountReconciliationService $service, FinancialReportViewDataService $viewData)
    {
        $type = $request->input('reconciliation_type', 'customers');
        $permission = in_array($type, ['vat_output', 'vat_input'], true) ? 'tax' : $type;
        abort_unless($request->user()->hasPermission('accounting.reconciliation.'.$permission), 403);

        return view('accounting.reports.reconciliation', ['report' => $service->report($type, $request->validated())] + $viewData->filters());
    }
}
