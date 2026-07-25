<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SalesCreditNoteRequest;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Services\SalesCreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalesCreditNoteController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', SalesCreditNote::class);

        return view('sales-credit-notes.index', ['notes' => SalesCreditNote::where('company_id', $tenant->companyId())->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with(['customer', 'invoice'])->latest()->paginate(30)]);
    }

    public function create(SalesInvoice $salesInvoice): View
    {
        $this->authorize('create', SalesCreditNote::class);

        return view('sales-credit-notes.form', ['invoice' => $salesInvoice->load('items')]);
    }

    public function store(SalesCreditNoteRequest $request, SalesCreditNoteService $service): RedirectResponse
    {
        $this->authorize('create', SalesCreditNote::class);
        $note = $service->create(SalesInvoice::findOrFail($request->validated('sales_invoice_id')), $request->safe()->except(['sales_invoice_id', 'items']), $request->validated('items'));

        return redirect()->route('sales-credit-notes.show', $note);
    }

    public function show(SalesCreditNote $salesCreditNote): View
    {
        $this->authorize('view', $salesCreditNote);

        return view('sales-credit-notes.show', ['note' => $salesCreditNote->load(['invoice', 'customer', 'items.invoiceItem'])]);
    }

    public function action(SalesCreditNote $salesCreditNote, string $action, SalesCreditNoteService $service): RedirectResponse
    {
        $this->authorize($action, $salesCreditNote);
        $action === 'approve' ? $service->approve($salesCreditNote) : $service->issue($salesCreditNote);

        return back()->with('success', 'Credit note action completed.');
    }

    public function print(SalesCreditNote $salesCreditNote): View
    {
        $this->authorize('print', $salesCreditNote);

        return view('sales-credit-notes.print', ['note' => $salesCreditNote->load(['invoice.branch.company', 'customer', 'items'])]);
    }
}
