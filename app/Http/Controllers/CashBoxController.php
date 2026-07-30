<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CashBoxActionRequest;
use App\Http\Requests\CashBoxCustodianRequest;
use App\Http\Requests\CashBoxRequest;
use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\Currency;
use App\Models\User;
use App\Services\CashBoxCustodianService;
use App\Services\CashBoxService;
use App\Services\TreasuryBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashBoxController extends Controller
{
    public function index(TenantContext $tenant, TreasuryBalanceService $balances): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.cash_boxes.view'), 403);
        $boxes = CashBox::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->with(['branch', 'currency', 'glAccount', 'custodians.user'])->orderBy('code')->get();

        return view('treasury.cash-boxes', [
            'cashBoxes' => $boxes,
            'balances' => $boxes->mapWithKeys(fn ($box) => [$box->id => $balances->cashBox($box)]),
            'branches' => $tenant->accessibleBranches(),
            'currencies' => Currency::query()->where('is_active', true)->get(),
            'glAccounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)
                ->where('is_posting', true)
                ->where('is_cash_account', true)
                ->orderBy('account_code')
                ->get(),
            'users' => User::query()->where('company_id', $tenant->companyId())->where('status', 'active')->get(),
        ]);
    }

    public function store(CashBoxRequest $request, CashBoxService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_boxes.create'), 403);
        $service->create($request->validated());

        return back()->with('success', 'تم إنشاء الخزينة كمسودة.');
    }

    public function update(CashBoxRequest $request, CashBox $cashBox, CashBoxService $service): RedirectResponse
    {
        $this->authorize('update', $cashBox);
        $service->update($cashBox, $request->validated());

        return back()->with('success', 'تم تحديث الخزينة.');
    }

    public function action(CashBoxActionRequest $request, CashBox $cashBox, string $action, CashBoxService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_boxes.'.$action), 403);
        $isReactivation = $action === 'activate' && $cashBox->status === 'suspended';
        $service->action($cashBox, $action, $request->validated('reason'));

        $message = match ($action) {
            'activate' => $isReactivation
                ? 'تمت إعادة تفعيل الخزينة بنجاح.'
                : 'تم تفعيل الخزينة بنجاح.',
            'suspend' => 'تم تعليق الخزينة بنجاح.',
            'close' => 'تم إغلاق الخزينة بنجاح.',
        };

        return back()->with('success', $message);
    }

    public function custodian(
        CashBoxCustodianRequest $request,
        CashBox $cashBox,
        CashBoxCustodianService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_boxes.manage_custodians'), 403);
        $service->assign($cashBox, $request->validated());

        return back()->with('success', 'تم تعيين أمين الخزينة.');
    }

    public function revoke(
        CashBoxCustodianRequest $request,
        CashBoxCustodian $cashBoxCustodian,
        CashBoxCustodianService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_boxes.manage_custodians'), 403);
        $service->revoke($cashBoxCustodian, $request->validated('reason'));

        return back()->with('success', 'تم إلغاء تعيين أمين الخزينة مع حفظ التاريخ.');
    }

    public function updateCustodian(
        CashBoxCustodianRequest $request,
        CashBoxCustodian $cashBoxCustodian,
        CashBoxCustodianService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_boxes.manage_custodians'), 403);
        $service->update($cashBoxCustodian, $request->validated());

        return back()->with('success', 'تم تحديث صلاحيات أمين الخزينة.');
    }
}
