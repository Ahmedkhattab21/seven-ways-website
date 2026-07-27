<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\ApprovalTask;
use App\Services\CentralApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('approvals.view'), 403);
        $query = ApprovalTask::with(['requester', 'approvable'])->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'));
        foreach (['module', 'status', 'priority', 'branch_id'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->input($field)));
        }

        return view('approvals.index', ['tasks' => $query->latest('requested_at')->paginate(50)]);
    }

    public function show(Request $request, ApprovalTask $approval, TenantContext $tenant): View
    {
        $this->assertVisible($request, $approval, $tenant);

        return view('approvals.show', ['task' => $approval->load(['requester', 'actions', 'approvable'])]);
    }

    public function decide(
        Request $request,
        ApprovalTask $approval,
        string $decision,
        CentralApprovalService $service,
        TenantContext $tenant
    ): RedirectResponse {
        $this->assertVisible($request, $approval, $tenant);
        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:500']]);
        $service->decide($approval, $decision, $data['reason'] ?? null);

        return redirect()->route('approvals.show', $approval)->with('success', 'تم تسجيل قرار الاعتماد.');
    }

    private function assertVisible(Request $request, ApprovalTask $task, TenantContext $tenant): void
    {
        abort_unless($request->user()->hasPermission('approvals.view')
            && $task->company_id === $tenant->companyId()
            && $tenant->accessibleBranches()->contains('id', $task->branch_id), 403);
    }
}
