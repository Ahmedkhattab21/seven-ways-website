<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankAdjustmentRequest;
use App\Models\Account;
use App\Models\BankAdjustment;
use App\Models\BankReconciliationSession;
use App\Services\BankAdjustmentService;
use App\Services\BankReconciliationScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankAdjustmentController extends Controller
{
    public function index(TenantContext $tenant, BankReconciliationScopeService $scope): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.bank_adjustments.view'), 403);

        return view('treasury.bank-adjustments', [
            'adjustments' => BankAdjustment::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->with(['bankAccount', 'journalEntry'])->latest('id')->paginate(30),
            'accounts' => $scope->accountQuery()->where('status', 'active')->get(),
            'offsetAccounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)->where('is_posting', true)->orderBy('account_code')->get(),
            'sessions' => BankReconciliationSession::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->whereIn('status', ['matching', 'reopened'])->get(),
        ]);
    }

    public function store(BankAdjustmentRequest $request, BankAdjustmentService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.bank_adjustments.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء تسوية البنك كمسودة.');
    }

    public function update(
        BankAdjustmentRequest $request,
        BankAdjustment $bankAdjustment,
        BankAdjustmentService $service
    ): RedirectResponse {
        $this->authorize('update', $bankAdjustment);
        $service->update($bankAdjustment, $request->validated());

        return back()->with('success', 'تم تحديث تسوية البنك.');
    }
}
