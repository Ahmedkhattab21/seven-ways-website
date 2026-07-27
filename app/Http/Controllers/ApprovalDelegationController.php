<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Services\ApprovalDelegationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApprovalDelegationController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('delegations.view'), 403);

        return view('approvals.delegations', [
            'delegations' => ApprovalDelegation::with(['delegator', 'delegate'])
                ->where('company_id', $tenant->companyId())->latest()->paginate(50),
            'users' => User::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'branches' => $tenant->accessibleBranches(),
        ]);
    }

    public function store(Request $request, ApprovalDelegationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('delegations.create'), 403);
        $data = $request->validate([
            'delegator_id' => ['required', 'integer'],
            'delegate_id' => ['required', 'integer', 'different:delegator_id'],
            'branch_id' => ['nullable', 'integer'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [Rule::in(['purchasing', 'treasury'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $service->create($data);

        return back()->with('success', 'تم إنشاء التفويض.');
    }

    public function cancel(
        Request $request,
        ApprovalDelegation $delegation,
        ApprovalDelegationService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('delegations.cancel'), 403);
        $service->cancel($delegation);

        return back()->with('success', 'تم إلغاء التفويض.');
    }
}
