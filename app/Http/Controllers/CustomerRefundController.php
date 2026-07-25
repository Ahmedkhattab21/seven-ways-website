<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CustomerRefundRequest;
use App\Models\CustomerRefund;
use App\Models\PaymentMethod;
use App\Models\SalesCreditNote;
use App\Services\CustomerRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerRefundController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', CustomerRefund::class);

        return view('customer-refunds.index', ['refunds' => CustomerRefund::where('company_id', $tenant->companyId())->with('customer')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', CustomerRefund::class);

        return view('customer-refunds.form', ['notes' => SalesCreditNote::where('company_id', $tenant->companyId())->whereIn('status', ['issued', 'partially_applied', 'applied'])->get(), 'methods' => PaymentMethod::where('company_id', $tenant->companyId())->where('is_active', true)->get()]);
    }

    public function store(CustomerRefundRequest $request, CustomerRefundService $service): RedirectResponse
    {
        $this->authorize('create', CustomerRefund::class);
        $refund = $service->create($request->validated());

        return redirect()->route('customer-refunds.show', $refund);
    }

    public function show(CustomerRefund $customerRefund): View
    {
        $this->authorize('view', $customerRefund);

        return view('customer-refunds.show', ['refund' => $customerRefund->load(['customer', 'creditNote', 'paymentMethod'])]);
    }

    public function action(CustomerRefund $customerRefund, string $action, CustomerRefundService $service): RedirectResponse
    {
        $this->authorize($action, $customerRefund);
        $action === 'approve' ? $service->approve($customerRefund) : $service->process($customerRefund);

        return back()->with('success', 'Refund action completed.');
    }
}
