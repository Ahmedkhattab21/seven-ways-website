<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SalesCreditNoteRequest;
use App\Models\SalesCreditNote;
use App\Models\SalesCreditNoteItem;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
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

    public function create(SalesInvoice $salesInvoice, TenantContext $tenant): View
    {
        $this->authorize('create', SalesCreditNote::class);
        abort_unless($salesInvoice->company_id === $tenant->companyId() && $tenant->user()->canAccessBranch($salesInvoice->branch), 403);

        $salesInvoice->load(['items.product', 'branch', 'customer', 'currency']);
        $creditedQuantities = SalesCreditNoteItem::query()
            ->selectRaw('sales_invoice_item_id, SUM(quantity) as credited_quantity')
            ->whereIn('sales_invoice_item_id', $salesInvoice->items->pluck('id'))
            ->whereHas('creditNote', fn ($query) => $query->whereIn('status', ['issued', 'partially_applied', 'applied', 'refunded']))
            ->groupBy('sales_invoice_item_id')
            ->pluck('credited_quantity', 'sales_invoice_item_id');

        return view('sales-credit-notes.form', [
            'invoice' => $salesInvoice,
            'creditedQuantities' => $creditedQuantities,
            'warehouses' => Warehouse::query()
                ->where('company_id', $salesInvoice->company_id)
                ->where('branch_id', $salesInvoice->branch_id)
                ->where('is_active', true)
                ->where('is_system', false)
                ->where('warehouse_type', '!=', 'transit')
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get(),
        ]);
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

        return view('sales-credit-notes.show', [
            'note' => $salesCreditNote->load([
                'invoice.branch', 'customer', 'currency', 'items.invoiceItem',
                'productReturns.warehouse', 'productReturns.stockMovement',
            ]),
        ]);
    }

    public function action(SalesCreditNote $salesCreditNote, string $action, SalesCreditNoteService $service): RedirectResponse
    {
        $this->authorize($action, $salesCreditNote);
        $action === 'approve' ? $service->approve($salesCreditNote) : $service->issue($salesCreditNote);

        return back()->with('success', $action === 'approve'
            ? 'تم اعتماد الإشعار الدائن بنجاح.'
            : 'تم إصدار الإشعار الدائن بنجاح.');
    }

    public function print(SalesCreditNote $salesCreditNote): View
    {
        $this->authorize('print', $salesCreditNote);

        return view('sales-credit-notes.print', ['note' => $salesCreditNote->load(['invoice.branch.company', 'customer', 'items'])]);
    }
}
