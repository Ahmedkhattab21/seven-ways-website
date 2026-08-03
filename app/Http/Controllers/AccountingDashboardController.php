<?php

namespace App\Http\Controllers;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AnalyticsReportRequest;
use App\Services\AccountingDashboardService;
use App\Services\FinancialReportViewDataService;
use Illuminate\View\View;

class AccountingDashboardController extends Controller
{
    public function __invoke(
        AnalyticsReportRequest $request,
        TenantContext $tenant,
        AccountingDashboardService $dashboard,
        FinancialReportViewDataService $viewData
    ): View {
        abort_unless($request->user()->hasPermission('accounting.accounts.view'), 403);
        $filters = ReportFilterData::from($request->validated(), $tenant);

        return view('accounting.dashboard', [
            'dashboard' => $dashboard->build($filters),
            'filters' => $filters,
            ...$viewData->filters(),
        ]);
    }
}
