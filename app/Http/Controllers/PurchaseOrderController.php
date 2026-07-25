<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PurchaseOrderActionRequest;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\PurchaseOrderApprovalService;
use App\Services\PurchaseOrderIssuanceService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return view('purchase-orders.index', ['documents' => PurchaseOrder::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with(['branch', 'supplier'])->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchase-orders.form', [
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'products' => Product::where('company_id', $tenant->companyId())->where('is_purchasable', true)->where('is_active', true)->get(),
            'requisitions' => PurchaseRequisition::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->whereIn('status', ['approved', 'partially_ordered'])->get(),
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('purchase-orders.show', $document)->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        return view('purchase-orders.show', ['document' => $purchaseOrder->load(['branch', 'supplier', 'currency', 'items.product', 'receipts'])]);
    }

    public function action(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder, string $action, PurchaseOrderApprovalService $approval, PurchaseOrderIssuanceService $issuance): RedirectResponse
    {
        $this->authorize($action, $purchaseOrder);
        match ($action) {
            'submit' => $approval->submit($purchaseOrder),
            'approve' => $approval->approve($purchaseOrder),
            'send' => $issuance->send($purchaseOrder),
            'cancel' => $approval->cancel($purchaseOrder),
        };

        return back()->with('success', 'Purchase order action completed.');
    }
}
