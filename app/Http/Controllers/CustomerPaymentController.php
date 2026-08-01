<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CustomerPaymentRequest;
use App\Http\Requests\PaymentAllocationRequest;
use App\Http\Requests\PaymentAllocationReversalRequest;
use App\Models\AppointmentDeposit;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Services\CustomerPaymentService;
use App\Services\OperationalDepositConversionService;
use App\Services\PaymentAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', CustomerPayment::class);

        return view('customer-payments.index', ['payments' => CustomerPayment::where('company_id', $tenant->companyId())->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with('customer')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant, CustomerPaymentService $service): View
    {
        $this->authorize('create', CustomerPayment::class);

        $cashBoxes = $service->availableCashBoxes();
        $cashSessions = $service->availableCashSessions($cashBoxes);

        return view('customer-payments.form', [
            'customers' => Customer::forUser($tenant->user())->orderBy('name')->get(),
            'methods' => PaymentMethod::query()
                ->where('company_id', $tenant->companyId())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'cashBoxes' => $cashBoxes,
            'cashSessions' => $cashSessions,
            'defaultPaymentDate' => $cashSessions->first()?->business_date->toDateString() ?? today()->toDateString(),
            'invoices' => SalesInvoice::query()
                ->where('company_id', $tenant->companyId())
                ->where('branch_id', $tenant->branchId())
                ->whereIn('status', ['issued', 'partially_paid', 'overdue', 'credited'])
                ->where('balance_due', '>', 0)
                ->with(['customer', 'currency'])
                ->latest('invoice_date')
                ->get(),
        ]);
    }

    public function store(CustomerPaymentRequest $request, CustomerPaymentService $service): RedirectResponse
    {
        $payment = $service->record($request->validated());

        return redirect()->route('customer-payments.show', $payment);
    }

    public function show(CustomerPayment $customerPayment): View
    {
        $this->authorize('view', $customerPayment);

        return view('customer-payments.show', [
            'payment' => $customerPayment->load([
                'customer', 'paymentMethod', 'cashBox', 'cashBoxSession', 'cashReceipt', 'intendedInvoice',
                'allocations.invoice',
            ]),
            'invoices' => SalesInvoice::query()
                ->where('company_id', $customerPayment->company_id)
                ->where('branch_id', $customerPayment->branch_id)
                ->where('customer_id', $customerPayment->customer_id)
                ->where('currency_id', $customerPayment->currency_id)
                ->whereIn('status', ['issued', 'partially_paid', 'overdue', 'credited'])
                ->where('balance_due', '>', 0)
                ->orderByDesc('invoice_date')
                ->get(),
        ]);
    }

    public function approve(CustomerPayment $customerPayment, CustomerPaymentService $service): RedirectResponse
    {
        $this->authorize('approve', $customerPayment);
        $service->approve($customerPayment);

        return back()->with('success', 'تم اعتماد الدفعة بنجاح.');
    }

    public function allocate(PaymentAllocationRequest $request, CustomerPayment $customerPayment, PaymentAllocationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('customer_payments.allocate'), 403);
        $service->allocate($customerPayment, SalesInvoice::findOrFail($request->validated('sales_invoice_id')), $request->validated('amount'));

        return back()->with('success', 'تم تخصيص الدفعة على الفاتورة بنجاح.');
    }

    public function reverse(PaymentAllocationReversalRequest $request, PaymentAllocation $paymentAllocation, PaymentAllocationService $service): RedirectResponse
    {
        $this->authorize('reverse', $paymentAllocation);
        $service->reverse($paymentAllocation, $request->validated('reason'));

        return back()->with('success', 'Allocation reversed.');
    }

    public function convert(Request $request, AppointmentDeposit $appointmentDeposit, OperationalDepositConversionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('customer_payments.record'), 403);
        $request->validate(['sales_invoice_id' => ['nullable', 'integer']]);
        $payment = $service->convert($appointmentDeposit, $request->sales_invoice_id ? SalesInvoice::findOrFail($request->sales_invoice_id) : null);

        return redirect()->route('customer-payments.show', $payment);
    }

    public function receipt(CustomerPayment $customerPayment): View
    {
        $this->authorize('view', $customerPayment);

        return view('customer-payments.receipt', ['payment' => $customerPayment->load(['branch.company', 'customer', 'paymentMethod', 'allocations.invoice'])]);
    }
}
