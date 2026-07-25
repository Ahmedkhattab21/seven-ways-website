<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SupplierInvoiceActionRequest;
use App\Http\Requests\SupplierInvoiceMatchingRequest;
use App\Http\Requests\SupplierInvoiceRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceMatch;
use App\Services\SupplierInvoiceApprovalService;
use App\Services\SupplierInvoiceMatchingService;
use App\Services\SupplierInvoicePostingService;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierInvoiceController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', SupplierInvoice::class);

        return view('supplier-invoices.index', ['documents' => SupplierInvoice::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with('supplier')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', SupplierInvoice::class);

        return view('supplier-invoices.form', [
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->get(),
            'orders' => PurchaseOrder::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->with('items.product')->get(),
            'receipts' => GoodsReceipt::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('status', 'posted')->with('items.product')->get(),
        ]);
    }

    public function store(SupplierInvoiceRequest $request, SupplierInvoiceService $service): RedirectResponse
    {
        $this->authorize('create', SupplierInvoice::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('supplier-invoices.show', $document)->with('success', 'Supplier invoice created.');
    }

    public function show(SupplierInvoice $supplierInvoice): View
    {
        $this->authorize('view', $supplierInvoice);

        return view('supplier-invoices.show', ['document' => $supplierInvoice->load(['supplier', 'currency', 'items.matches', 'allocations.payment', 'creditNotes'])]);
    }

    public function action(SupplierInvoiceActionRequest $request, SupplierInvoice $supplierInvoice, string $action, SupplierInvoiceApprovalService $approval, SupplierInvoicePostingService $posting): RedirectResponse
    {
        $this->authorize($action, $supplierInvoice);
        match ($action) {
            'submit' => $approval->submit($supplierInvoice),
            'approve' => $approval->approve($supplierInvoice),
            'post' => $posting->post($supplierInvoice),
        };

        return back()->with('success', 'Supplier invoice action completed.');
    }

    public function approveVariance(SupplierInvoiceMatchingRequest $request, SupplierInvoice $supplierInvoice, SupplierInvoiceMatchingService $service): RedirectResponse
    {
        $this->authorize('approve', $supplierInvoice);
        $match = SupplierInvoiceMatch::whereKey($request->integer('match_id'))
            ->whereHas('invoiceItem', fn ($query) => $query->where('supplier_invoice_id', $supplierInvoice->id))->firstOrFail();
        $service->approveVariance($match, $request->validated('approval_reason'), $request->user()->id);

        return back()->with('success', 'Matching variance approved.');
    }
}
