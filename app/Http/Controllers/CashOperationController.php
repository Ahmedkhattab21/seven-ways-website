<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CashPaymentActionRequest;
use App\Http\Requests\CashPaymentRequest;
use App\Http\Requests\CashReceiptActionRequest;
use App\Http\Requests\CashReceiptRequest;
use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashPayment;
use App\Models\CashReceipt;
use App\Services\CashOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashOperationController extends Controller
{
    public function receipts(TenantContext $tenant): View
    {
        return $this->index($tenant, 'receipt');
    }

    public function payments(TenantContext $tenant): View
    {
        return $this->index($tenant, 'payment');
    }

    public function storeReceipt(CashReceiptRequest $request, CashOperationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_receipts.create'), 403);
        $service->create('receipt', $request->validated());

        return back()->with('success', 'تم إنشاء المقبوض النقدي.');
    }

    public function storePayment(CashPaymentRequest $request, CashOperationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_payments.create'), 403);
        $service->create('payment', $request->validated());

        return back()->with('success', 'تم إنشاء المدفوع النقدي.');
    }

    public function receiptAction(
        CashReceiptActionRequest $request,
        CashReceipt $cashReceipt,
        string $action,
        CashOperationService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_receipts.'.$action), 403);
        $service->action($cashReceipt, $action, $request->validated('reason'), $request->validated('date'));

        return back()->with('success', 'تم تحديث المقبوض النقدي.');
    }

    public function paymentAction(
        CashPaymentActionRequest $request,
        CashPayment $cashPayment,
        string $action,
        CashOperationService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_payments.'.$action), 403);
        $service->action($cashPayment, $action, $request->validated('reason'), $request->validated('date'));

        return back()->with('success', 'تم تحديث المدفوع النقدي.');
    }

    private function index(TenantContext $tenant, string $direction): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.cash_'.$direction.'s.view'), 403);
        $model = $direction === 'receipt' ? CashReceipt::class : CashPayment::class;

        return view('treasury.cash-operations', [
            'direction' => $direction,
            'operations' => $model::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->latest('id')->paginate(30),
            'cashBoxes' => CashBox::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->where('status', 'active')->get(),
            'accounts' => Account::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)->where('is_posting', true)->get(),
            'company' => $tenant->company(),
            'branches' => $tenant->accessibleBranches(),
        ]);
    }
}
