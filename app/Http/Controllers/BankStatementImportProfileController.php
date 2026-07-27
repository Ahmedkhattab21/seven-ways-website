<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankStatementImportProfileRequest;
use App\Models\BankStatementImportProfile;
use App\Services\BankReconciliationScopeService;
use App\Services\BankStatementImportProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankStatementImportProfileController extends Controller
{
    public function index(TenantContext $tenant, BankReconciliationScopeService $scope): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.bank_statements.view'), 403);

        return view('treasury.bank-statement-profiles', [
            'profiles' => BankStatementImportProfile::query()->where('company_id', $tenant->companyId())
                ->where(fn ($query) => $query->whereNull('bank_account_id')->orWhereIn('bank_account_id', $scope->accountIds()))
                ->with('bankAccount')->orderBy('name')->get(),
            'accounts' => $scope->accountQuery()->orderBy('account_name')->get(),
        ]);
    }

    public function store(
        BankStatementImportProfileRequest $request,
        BankStatementImportProfileService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_statements.import'), 403);
        $service->save($request->validated());

        return back()->with('success', 'تم حفظ ملف تعريف CSV.');
    }

    public function update(
        BankStatementImportProfileRequest $request,
        BankStatementImportProfile $bankStatementImportProfile,
        BankStatementImportProfileService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_statements.import'), 403);
        $service->save($request->validated(), $bankStatementImportProfile);

        return back()->with('success', 'تم تحديث ملف تعريف CSV.');
    }
}
