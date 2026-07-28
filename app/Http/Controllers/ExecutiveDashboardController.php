<?php

namespace App\Http\Controllers;

use App\Analytics\ReportFilterData;
use App\Analytics\ReportRegistry;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AnalyticsReportRequest;
use App\Services\ExecutiveDashboardService;
use App\Services\FinancialReportViewDataService;
use Illuminate\View\View;

class ExecutiveDashboardController extends Controller
{
    public function __invoke(
        AnalyticsReportRequest $request,
        TenantContext $tenant,
        ExecutiveDashboardService $dashboard,
        ReportRegistry $registry,
        FinancialReportViewDataService $viewData
    ): View {
        abort_unless(
            $request->user()->hasRole('system_admin')
                || $request->user()->hasPermission('dashboards.executive.view'),
            403
        );
        $filters = ReportFilterData::from($request->validated(), $tenant);

        return view('analytics.executive-dashboard', [
            'dashboard' => $dashboard->build($filters),
            'filters' => $filters,
            'reports' => $registry->all(),
            ...$viewData->filters(),
        ]);
    }
}
