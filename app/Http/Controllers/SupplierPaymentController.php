<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SupplierPaymentAllocationRequest;
use App\Http\Requests\SupplierPaymentAllocationReversalRequest;
use App\Http\Requests\SupplierPaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Services\SupplierPaymentAllocationService;
use App\Services\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', SupplierPayment::class);

        return view('supplier-payments.index', ['documents' => SupplierPayment::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with('supplier')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', SupplierPayment::class);

        return view('supplier-payments.form', [
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->get(),
            'methods' => PaymentMethod::where('company_id', $tenant->companyId())->where('is_active', true)->get(),
        ]);
    }

    public function store(SupplierPaymentRequest $request, SupplierPaymentService $service): RedirectResponse
    {
        $this->authorize('create', SupplierPayment::class);
        $payment = $service->create($request->validated());

        return redirect()->route('supplier-payments.show', $payment)->with('success', 'Supplier payment created.');
    }

    public function show(SupplierPayment $supplierPayment): View
    {
        $this->authorize('view', $supplierPayment);

        return view('supplier-payments.show', [
            'document' => $supplierPayment->load(['supplier', 'currency', 'paymentMethod', 'allocations.invoice']),
            'invoices' => SupplierInvoice::where('supplier_id', $supplierPayment->supplier_id)
                ->where('currency_id', $supplierPayment->currency_id)->where('balance_due', '>', 0)
                ->whereIn('status', ['posted', 'partially_paid', 'overdue'])->get(),
        ]);
    }

    public function action(SupplierPayment $supplierPayment, string $action, SupplierPaymentService $service): RedirectResponse
    {
        $this->authorize($action, $supplierPayment);
        match ($action) {
            'approve' => $service->approve($supplierPayment),
            'process' => $service->process($supplierPayment),
        };

        return back()->with('success', 'Supplier payment action completed.');
    }

    public function allocate(SupplierPaymentAllocationRequest $request, SupplierPayment $supplierPayment, SupplierPaymentAllocationService $service): RedirectResponse
    {
        $this->authorize('allocate', $supplierPayment);
        $invoice = SupplierInvoice::findOrFail($request->integer('supplier_invoice_id'));
        $service->allocate($supplierPayment, $invoice, (string) $request->validated('amount'));

        return back()->with('success', 'Supplier payment allocated.');
    }

    public function reverse(SupplierPaymentAllocationReversalRequest $request, SupplierPaymentAllocation $supplierPaymentAllocation, SupplierPaymentAllocationService $service): RedirectResponse
    {
        $this->authorize('allocate', $supplierPaymentAllocation->payment);
        abort_unless($request->user()->hasPermission('supplier_payments.reverse_allocation'), 403);
        $service->reverse($supplierPaymentAllocation, $request->validated('reason'));

        return back()->with('success', 'Supplier payment allocation reversed.');
    }
}
