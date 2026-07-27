<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\TreasuryApprovalLimitRequest;
use App\Models\Role;
use App\Models\TreasuryApprovalLimit;
use App\Models\User;
use App\Services\TreasuryApprovalLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TreasuryApprovalLimitController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.approval_limits.view'), 403);

        return view('treasury.approval-limits', [
            'limits' => TreasuryApprovalLimit::query()->where('company_id', $tenant->companyId())
                ->latest('id')->paginate(30),
            'roles' => Role::query()->where(fn ($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $tenant->companyId()))->where('is_active', true)->get(),
            'users' => User::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'branches' => $tenant->accessibleBranches(), 'company' => $tenant->company(),
        ]);
    }

    public function store(
        TreasuryApprovalLimitRequest $request,
        TreasuryApprovalLimitService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.approval_limits.manage'), 403);
        $service->save($request->validated());

        return back()->with('success', 'تم حفظ حد الاعتماد.');
    }

    public function update(
        TreasuryApprovalLimitRequest $request,
        TreasuryApprovalLimit $treasuryApprovalLimit,
        TreasuryApprovalLimitService $service
    ): RedirectResponse {
        $this->authorize('update', $treasuryApprovalLimit);
        $service->save($request->validated(), $treasuryApprovalLimit);

        return back()->with('success', 'تم تحديث حد الاعتماد.');
    }
}
