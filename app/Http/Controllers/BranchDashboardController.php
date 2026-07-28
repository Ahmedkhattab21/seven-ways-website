<?php

namespace App\Http\Controllers;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AnalyticsReportRequest;
use App\Services\ExecutiveDashboardService;
use Illuminate\View\View;

class BranchDashboardController extends Controller
{
    public function __invoke(
        AnalyticsReportRequest $request,
        TenantContext $tenant,
        ExecutiveDashboardService $dashboard
    ): View {
        abort_unless(
            $request->user()->hasRole('system_admin')
                || $request->user()->hasPermission('dashboards.branch.view'),
            403
        );
        $validated = $request->validated();
        $branches = $tenant->accessibleBranches();
        if ($request->filled('branch_id')) {
            $branches = $branches->where('id', (int) $request->input('branch_id'));
            abort_if($branches->isEmpty(), 403);
        } elseif (! $request->user()->hasRole('system_admin')
            && ! $request->user()->hasPermission('reports.view_all_branches')) {
            $branches = $branches->where('id', $tenant->branchId());
        }

        $dashboards = $branches->mapWithKeys(function ($branch) use ($validated, $tenant, $dashboard) {
            $filters = ReportFilterData::from([...$validated, 'branch_id' => $branch->id], $tenant);

            return [$branch->id => [
                'branch' => $branch,
                'data' => $dashboard->build($filters),
            ]];
        });

        return view('analytics.branch-dashboard', compact('dashboards', 'branches'));
    }
}
