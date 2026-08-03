<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\SalesInvoiceActionRequest;
use App\Http\Requests\SalesInvoiceRequest;
use App\Http\Requests\SalesProductReturnRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\ProductPricingService;
use App\Services\QuotationToSalesInvoiceService;
use App\Services\SalesInvoiceApprovalService;
use App\Services\SalesInvoiceIssuanceService;
use App\Services\SalesInvoiceService;
use App\Services\SalesProductReturnService;
use App\Services\WorkOrderToInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', SalesInvoice::class);

        return view('sales-invoices.index', ['invoices' => SalesInvoice::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with(['customer', 'branch'])->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant, ProductPricingService $productPricing): View
    {
        $this->authorize('create', SalesInvoice::class);

        $branch = $tenant->branch();
        $invoiceDate = today()->toDateString();
        $products = Product::query()
            ->where('company_id', $tenant->companyId())
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->whereNotIn('tracking_type', ['roll', 'scrap'])
            ->whereHas('branchProducts', fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->where('is_available', true)
                ->where('is_sellable', true))
            ->where(fn ($query) => $query->whereNotNull('default_sale_price')
                ->orWhereHas('branchPrices', fn ($prices) => $prices
                    ->where('branch_id', $branch->id)
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', $invoiceDate)
                    ->where(fn ($dates) => $dates->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $invoiceDate))))
            ->with(['saleUnit:id,name,symbol', 'brand:id,name'])
            ->withSum(['stockBalances as branch_stock_available' => fn ($query) => $query
                ->where('branch_id', $branch->id)], 'available_quantity')
            ->orderBy('name')
            ->get()
            ->each(function (Product $product) use ($productPricing, $branch, $invoiceDate) {
                $price = $productPricing->resolvePrice($product, $branch, $invoiceDate);
                $product->setAttribute('resolved_base_price', $price['base_price']);
                $product->setAttribute('resolved_final_price', $price['final_price']);
                $product->setAttribute('resolved_promotion_name', $price['promotion_name']);
                $product->setAttribute('default_sales_warehouse_id', $price['warehouse_id']);
            });

        return view('sales-invoices.form', [
            'customers' => Customer::forUser($tenant->user())->where('status', 'active')->with('vehicles')->get(),
            'vehicles' => Vehicle::forUser($tenant->user())->where('status', 'active')->with('customer')->get(),
            'products' => $products,
            'warehouses' => Warehouse::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('is_system', false)->where('is_active', true)->get(),
        ]);
    }

    public function store(SalesInvoiceRequest $request, SalesInvoiceService $service): RedirectResponse
    {
        $this->authorize('create', SalesInvoice::class);
        $invoice = $service->createProductOnlyDirect($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('sales-invoices.show', $invoice)->with('success', 'Invoice draft created.');
    }

    public function fromWorkOrder(WorkOrder $workOrder, WorkOrderToInvoiceService $service): RedirectResponse
    {
        $this->authorize('create', SalesInvoice::class);
        $invoice = $service->create($workOrder);

        return redirect()->route('sales-invoices.show', $invoice)->with('success', 'Invoice draft created from work order.');
    }

    public function fromQuotation(Quotation $quotation, QuotationToSalesInvoiceService $service): RedirectResponse
    {
        $this->authorize('create', SalesInvoice::class);
        $invoice = $service->convert($quotation);

        return redirect()->route('sales-invoices.show', $invoice)
            ->with('success', 'تم تحويل عرض السعر إلى فاتورة مبيعات.');
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $this->authorize('view', $salesInvoice);

        return view('sales-invoices.show', ['invoice' => $salesInvoice->load(['company', 'branch', 'customer', 'vehicle', 'workOrder', 'currency', 'items.product.brand', 'items.warehouse', 'items.issuedMovement', 'allocations.payment', 'creditNotes', 'shares.generatedBy'])]);
    }

    public function action(SalesInvoiceActionRequest $request, SalesInvoice $salesInvoice, string $action, SalesInvoiceApprovalService $approval, SalesInvoiceIssuanceService $issuance): RedirectResponse
    {
        $this->authorize($action, $salesInvoice);
        match ($action) {
            'submit' => $approval->submit($salesInvoice),
            'approve' => $approval->approve($salesInvoice),
            'issue' => $issuance->issue($salesInvoice),
            'cancel' => $approval->cancel($salesInvoice),
            'void' => $approval->voidInvoice($salesInvoice, $request->validated('reason')),
        };

        return back()->with('success', 'تم تنفيذ إجراء الفاتورة بنجاح.');
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $this->authorize('print', $salesInvoice);

        return view('sales-invoices.print', ['invoice' => $salesInvoice->load(['company', 'branch', 'customer', 'vehicle', 'workOrder', 'currency', 'items.product.brand'])]);
    }

    public function returnProduct(SalesProductReturnRequest $request, SalesInvoiceItem $salesInvoiceItem, SalesProductReturnService $service): RedirectResponse
    {
        $this->authorize('create', SalesCreditNote::class);
        $warehouse = Warehouse::query()
            ->whereKey($request->integer('warehouse_id'))
            ->where('company_id', $salesInvoiceItem->invoice->company_id)
            ->where('branch_id', $salesInvoiceItem->invoice->branch_id)
            ->where('is_active', true)
            ->where('is_system', false)
            ->firstOrFail();
        $note = $service->return(
            $salesInvoiceItem,
            $warehouse,
            (string) $request->validated('quantity'),
            $request->validated('reason'),
            $request->validated('idempotency_key')
        );

        return redirect()->route('sales-credit-notes.show', $note)->with('success', 'تم حفظ طلب مرتجع المنتج كمسودة.');
    }
}
