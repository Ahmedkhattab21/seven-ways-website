<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\GoodsReceiptActionRequest;
use App\Http\Requests\GoodsReceiptInspectionRequest;
use App\Http\Requests\GoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\GoodsReceiptInspectionService;
use App\Services\GoodsReceiptPostingService;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return view('goods-receipts.index', ['documents' => GoodsReceipt::where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->with(['supplier', 'warehouse'])->latest()->paginate(30)]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('goods-receipts.form', [
            'orders' => PurchaseOrder::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->whereIn('status', ['sent', 'partially_received'])->with('items.product')->get(),
            'suppliers' => Supplier::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'warehouses' => Warehouse::where('company_id', $tenant->companyId())->where('branch_id', $tenant->branchId())->where('is_system', false)->where('warehouse_type', '!=', 'transit')->where('is_active', true)->get(),
        ]);
    }

    public function store(GoodsReceiptRequest $request, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('create', GoodsReceipt::class);
        $document = $service->create($request->safe()->except('items'), $request->validated('items'));

        return redirect()->route('goods-receipts.show', $document)->with('success', 'Goods receipt created.');
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('goods-receipts.show', ['document' => $goodsReceipt->load([
            'supplier', 'warehouse', 'purchaseOrder', 'items.product', 'items.createdRolls', 'attachments',
        ])]);
    }

    public function receive(GoodsReceiptActionRequest $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        $service->receive($goodsReceipt);

        return back()->with('success', 'Receipt marked received.');
    }

    public function inspect(GoodsReceiptInspectionRequest $request, GoodsReceipt $goodsReceipt, GoodsReceiptInspectionService $service): RedirectResponse
    {
        $this->authorize('inspect', $goodsReceipt);
        $service->inspect($goodsReceipt, $request->validated('items'));

        return back()->with('success', 'Receipt inspection completed.');
    }

    public function post(GoodsReceiptActionRequest $request, GoodsReceipt $goodsReceipt, GoodsReceiptPostingService $service): RedirectResponse
    {
        $this->authorize('post', $goodsReceipt);
        $service->post($goodsReceipt);

        return back()->with('success', 'Goods receipt posted.');
    }
}
