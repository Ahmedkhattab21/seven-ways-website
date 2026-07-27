<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\MerchantSettlementActionRequest;
use App\Http\Requests\MerchantSettlementRequest;
use App\Models\BankAccount;
use App\Models\CustomerPayment;
use App\Models\MerchantSettlement;
use App\Models\PaymentMethod;
use App\Services\MerchantSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MerchantSettlementController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.merchant_settlements.view'), 403);

        return view('treasury.merchant-settlements', [
            'settlements' => MerchantSettlement::query()->where('company_id', $tenant->companyId())
                ->where(fn ($q) => $q->whereNull('branch_id')
                    ->orWhereIn('branch_id', $tenant->accessibleBranches()->pluck('id')))
                ->with('lines')->latest('id')->paginate(30),
            'bankAccounts' => BankAccount::query()->where('company_id', $tenant->companyId())
                ->where('status', 'active')->get(),
            'paymentMethods' => PaymentMethod::query()->where(fn ($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $tenant->companyId()))->where('is_cash', false)->where('is_active', true)->get(),
            'sources' => CustomerPayment::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->whereNotIn('status', ['cancelled'])->latest('id')->limit(100)->get(),
            'branches' => $tenant->accessibleBranches(), 'company' => $tenant->company(),
        ]);
    }

    public function store(MerchantSettlementRequest $request, MerchantSettlementService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.merchant_settlements.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء تسوية التاجر من مصادر التحصيل.');
    }

    public function action(
        MerchantSettlementActionRequest $request,
        MerchantSettlement $merchantSettlement,
        string $action,
        MerchantSettlementService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.merchant_settlements.'.$action), 403);
        $service->action($merchantSettlement, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث تسوية التاجر.');
    }
}
