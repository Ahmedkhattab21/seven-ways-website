<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\TreasuryMappingRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use App\Services\TreasuryMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TreasuryMappingController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.mappings.view'), 403);

        return view('treasury.mappings', [
            'mappings' => PaymentMethodAccountMapping::query()->where('company_id', $tenant->companyId())
                ->with(['paymentMethod', 'branch', 'account', 'bankAccount', 'cashBox'])
                ->orderBy('payment_method_id')->get(),
            'paymentMethods' => PaymentMethod::query()->where('company_id', $tenant->companyId())->where('is_active', true)->get(),
            'branches' => $tenant->accessibleBranches(),
            'accounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_posting', true)->where('is_active', true)->get(),
            'bankAccounts' => BankAccount::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'cashBoxes' => CashBox::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->where('status', 'active')->get(),
        ]);
    }

    public function store(TreasuryMappingRequest $request, TreasuryMappingService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.mappings.update'), 403);
        $service->save($request->validated());

        return back()->with('success', 'تم تحديث توجيه وسيلة الدفع.');
    }
}
