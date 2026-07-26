<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AccountingSettingsRequest;
use App\Http\Requests\BranchAccountingSettingsRequest;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Services\AccountingSettingsService;
use App\Services\BranchAccountingSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingSettingsController extends Controller
{
    public function edit(TenantContext $tenant): View
    {
        $settings = AccountingSetting::where('company_id', $tenant->companyId())->firstOrFail();
        $this->authorize('view', $settings);

        return view('accounting.settings.edit', [
            'settings' => $settings, 'currencies' => Currency::where('is_active', true)->get(),
            'years' => FiscalYear::where('company_id', $tenant->companyId())->get(),
            'branches' => $tenant->accessibleBranches(),
            'mappings' => BranchAccountingSetting::where('company_id', $tenant->companyId())->get()->keyBy('branch_id'),
            'accounts' => Account::where('company_id', $tenant->companyId())->where('is_active', true)->where('is_posting', true)->get(),
            'costCenters' => CostCenter::where('company_id', $tenant->companyId())->where('is_active', true)->where('is_posting', true)->get(),
        ]);
    }

    public function update(AccountingSettingsRequest $request, AccountingSettingsService $service, TenantContext $tenant): RedirectResponse
    {
        $settings = AccountingSetting::where('company_id', $tenant->companyId())->firstOrFail();
        $this->authorize('update', $settings);
        $service->update($request->validated());

        return back()->with('success', 'تم تحديث إعدادات المحاسبة.');
    }

    public function branch(BranchAccountingSettingsRequest $request, Branch $branch, BranchAccountingSettingsService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.branch_mappings.update'), 403);
        $service->update($branch, $request->validated());

        return back()->with('success', 'تم تحديث ربط حسابات الفرع.');
    }
}
