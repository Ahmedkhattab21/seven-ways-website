<?php

namespace App\Http\Controllers;

use App\Analytics\ReportFilterData;
use App\Analytics\ReportRegistry;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AnalyticsReportRequest;
use App\Services\AnalyticsReportService;
use App\Services\FinancialReportViewDataService;
use Illuminate\View\View;

class AnalyticsReportController extends Controller
{
    public function __invoke(
        AnalyticsReportRequest $request,
        string $report,
        TenantContext $tenant,
        ReportRegistry $registry,
        AnalyticsReportService $service,
        FinancialReportViewDataService $viewData
    ): View {
        $definition = $registry->get($report);
        $this->authorizeReport($request->user(), $definition['permission']);
        $filters = ReportFilterData::from($request->validated(), $tenant);
        $result = $service->run($report, $filters);
        $rows = $result->rows;
        if ($request->filled('sort')) {
            abort_unless(in_array($request->input('sort'), $definition['sort_fields'], true), 422);
            $rows = $request->input('direction') === 'desc'
                ? $rows->sortByDesc($request->input('sort'))->values()
                : $rows->sortBy($request->input('sort'))->values();
        }

        return view('analytics.report', [
            'definition' => $definition,
            'result' => $result,
            'rows' => $rows,
            'filters' => $filters,
            ...$viewData->filters(),
        ]);
    }

    private function authorizeReport($user, string $permission): void
    {
        abort_unless($user->hasRole('system_admin') || $user->hasPermission($permission), 403);
    }
}
