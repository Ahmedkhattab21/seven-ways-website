<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PurchaseReturnActionRequest;
use App\Http\Requests\PurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseReturnPostingService;
use App\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        return view('purchase-returns.index', ['documents' => PurchaseReturn::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with('supplier')->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', PurchaseReturn::class);

        return view('purchase-returns.form', [
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->get(),
            'receipts' => GoodsReceipt::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('status', 'posted')->with('items.product')->get(),
            'warehouses' => Warehouse::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('is_system', false)->where('is_active', true)->get(),
        ]);
    }

    public function store(PurchaseReturnRequest $request, PurchaseReturnService $service): RedirectResponse
    {
        $this->authorize('create', PurchaseReturn::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('purchase-returns.show', $document)->with('success', 'Purchase return created.');
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('view', $purchaseReturn);

        return view('purchase-returns.show', ['document' => $purchaseReturn->load(['supplier', 'warehouse', 'items.receiptItem.product', 'items.roll'])]);
    }

    public function action(PurchaseReturnActionRequest $request, PurchaseReturn $purchaseReturn, string $action, PurchaseReturnService $service, PurchaseReturnPostingService $posting): RedirectResponse
    {
        $this->authorize($action, $purchaseReturn);
        match ($action) {
            'submit' => $service->submit($purchaseReturn),
            'approve' => $service->approve($purchaseReturn),
            'post' => $posting->post($purchaseReturn),
        };

        return back()->with('success', 'Purchase return action completed.');
    }
}
