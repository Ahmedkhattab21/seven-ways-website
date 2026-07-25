<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PurchaseRequisitionActionRequest;
use App\Http\Requests\PurchaseRequisitionRequest;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\PurchaseRequisitionApprovalService;
use App\Services\PurchaseRequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseRequisitionController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', PurchaseRequisition::class);

        return view('purchase-requisitions.index', ['documents' => PurchaseRequisition::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with('branch')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', PurchaseRequisition::class);

        return view('purchase-requisitions.form', [
            'products' => Product::where('company_id', $tenant->companyId())->where('is_purchasable', true)->where('is_active', true)->get(),
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
        ]);
    }

    public function store(PurchaseRequisitionRequest $request, PurchaseRequisitionService $service): RedirectResponse
    {
        $this->authorize('create', PurchaseRequisition::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('purchase-requisitions.show', $document)->with('success', 'Purchase requisition created.');
    }

    public function show(PurchaseRequisition $purchaseRequisition): View
    {
        $this->authorize('view', $purchaseRequisition);

        return view('purchase-requisitions.show', ['document' => $purchaseRequisition->load(['branch', 'items.product', 'items.preferredSupplier'])]);
    }

    public function action(PurchaseRequisitionActionRequest $request, PurchaseRequisition $purchaseRequisition, string $action, PurchaseRequisitionApprovalService $service): RedirectResponse
    {
        $this->authorize($action, $purchaseRequisition);
        match ($action) {
            'submit' => $service->submit($purchaseRequisition),
            'approve' => $service->approve($purchaseRequisition, $request->validated('approved_quantities', []), $request->validated('reason')),
            'reject' => $service->reject($purchaseRequisition, $request->validated('reason')),
            'cancel' => $service->cancel($purchaseRequisition),
        };

        return back()->with('success', 'Purchase requisition action completed.');
    }
}
