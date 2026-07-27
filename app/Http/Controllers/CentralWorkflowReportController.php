<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\ApprovalTask;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CentralWorkflowReportController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('approvals.view'), 403);
        $branches = $tenant->accessibleBranches()->pluck('id');
        $approvalBase = ApprovalTask::where('company_id', $tenant->companyId())->whereIn('branch_id', $branches);

        return view('approvals.reports', [
            'byStatus' => (clone $approvalBase)->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')->pluck('total', 'status'),
            'byModule' => (clone $approvalBase)->select('module', DB::raw('count(*) as total'))
                ->groupBy('module')->pluck('total', 'module'),
            'overdue' => (clone $approvalBase)->where('status', 'pending')->where('due_at', '<', now())->count(),
            'delegated' => (clone $approvalBase)->whereNotNull('delegation_id')->count(),
            'notificationStats' => SystemNotification::where('company_id', $tenant->companyId())
                ->where('user_id', $request->user()->id)
                ->selectRaw('count(*) as total, sum(case when read_at is null then 1 else 0 end) as unread')->first(),
        ]);
    }
}
