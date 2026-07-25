<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SupplierCreditNoteRequest;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Services\SupplierCreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierCreditNoteController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', SupplierCreditNote::class);

        return view('supplier-credit-notes.index', ['documents' => SupplierCreditNote::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', SupplierCreditNote::class);

        return view('supplier-credit-notes.form', [
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->get(),
            'invoices' => SupplierInvoice::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('balance_due', '>', 0)->get(),
            'returns' => PurchaseReturn::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('status', 'posted')->get(),
        ]);
    }

    public function store(SupplierCreditNoteRequest $request, SupplierCreditNoteService $service): RedirectResponse
    {
        $this->authorize('create', SupplierCreditNote::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('supplier-credit-notes.show', $document)->with('success', 'Supplier credit note created.');
    }

    public function show(SupplierCreditNote $supplierCreditNote): View
    {
        $this->authorize('view', $supplierCreditNote);

        return view('supplier-credit-notes.show', ['document' => $supplierCreditNote->load(['invoice', 'items'])]);
    }

    public function action(SupplierCreditNote $supplierCreditNote, string $action, SupplierCreditNoteService $service): RedirectResponse
    {
        $this->authorize($action, $supplierCreditNote);
        match ($action) {
            'approve' => $service->approve($supplierCreditNote),
            'post' => $service->post($supplierCreditNote),
        };

        return back()->with('success', 'Supplier credit action completed.');
    }
}
