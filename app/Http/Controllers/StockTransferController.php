<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\StockTransferApprovalRequest;
use App\Http\Requests\StockTransferCancellationRequest;
use App\Http\Requests\StockTransferDiscrepancyRequest;
use App\Http\Requests\StockTransferPreparationRequest;
use App\Http\Requests\StockTransferReceivingRequest;
use App\Http\Requests\StockTransferRequest;
use App\Http\Requests\StockTransferShipmentRequest;
use App\Models\InventoryRoll;
use App\Models\Product;
use App\Models\RollScrap;
use App\Models\StockTransfer;
use App\Models\StockTransferDiscrepancy;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\StockTransferApprovalService;
use App\Services\StockTransferCancellationService;
use App\Services\StockTransferPreparationService;
use App\Services\StockTransferReceivingService;
use App\Services\StockTransferReversalService;
use App\Services\StockTransferService;
use App\Services\StockTransferShipmentService;
use App\Services\TransferDiscrepancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $branchIds = $tenant->accessibleBranches()->pluck('id');
        $transfers = StockTransfer::query()->where('company_id', $tenant->companyId())
            ->when(! $tenant->user()->isCompanyAdministrator(), fn ($q) => $q->where(fn ($q) => $q->whereIn('from_branch_id', $branchIds)->orWhereIn('to_branch_id', $branchIds)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('transfer_type'), fn ($q) => $q->where('transfer_type', $request->transfer_type))
            ->when($request->filled('from_branch_id'), fn ($q) => $q->where('from_branch_id', $request->integer('from_branch_id')))
            ->when($request->filled('to_branch_id'), fn ($q) => $q->where('to_branch_id', $request->integer('to_branch_id')))
            ->with(['fromBranch', 'toBranch', 'fromWarehouse', 'toWarehouse', 'requester'])->withCount('items')
            ->latest('requested_at')->paginate(20)->withQueryString();
        $summary = [
            'pending_approval' => StockTransfer::where('company_id', $tenant->companyId())->where('status', 'pending_approval')->count(),
            'preparation' => StockTransfer::where('company_id', $tenant->companyId())->whereIn('status', ['approved', 'preparing'])->count(),
            'in_transit' => StockTransfer::where('company_id', $tenant->companyId())->whereIn('status', ['shipped', 'partially_received'])->count(),
            'discrepancies' => StockTransferDiscrepancy::whereHas('transfer', fn ($q) => $q->where('company_id', $tenant->companyId()))->where('status', 'open')->count(),
            'late' => StockTransfer::where('company_id', $tenant->companyId())->whereNotIn('status', ['received', 'cancelled', 'reversed'])
                ->where('expected_delivery_at', '<', now())->count(),
        ];

        return view('stock-transfers.index', ['branches' => $tenant->accessibleBranches()] + compact('transfers', 'summary'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('stock-transfers.form', $this->formData($tenant) + ['transfer' => new StockTransfer]);
    }

    public function store(StockTransferRequest $request, StockTransferService $service): RedirectResponse
    {
        $transfer = $service->create($request->validated());

        return redirect()->route('stock-transfers.show', $transfer)->with('success', 'تم إنشاء طلب التحويل.');
    }

    public function edit(StockTransfer $stockTransfer, TenantContext $tenant): View
    {
        $this->authorize('update', $stockTransfer);

        return view('stock-transfers.form', $this->formData($tenant) + ['transfer' => $stockTransfer->load('items')]);
    }

    public function update(StockTransferRequest $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('update', $stockTransfer);
        $service->update($stockTransfer, $request->validated());

        return redirect()->route('stock-transfers.show', $stockTransfer)->with('success', 'تم تحديث مسودة التحويل.');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);
        $stockTransfer->load([
            'fromBranch', 'toBranch', 'fromWarehouse', 'toWarehouse', 'transitWarehouse',
            'requester', 'approver', 'shipper', 'receiver', 'items.product', 'items.roll',
            'items.scrap', 'discrepancies.item',
        ]);

        return view('stock-transfers.show', ['transfer' => $stockTransfer]);
    }

    public function submit(StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('update', $stockTransfer);
        $service->submit($stockTransfer);

        return back()->with('success', 'تم تقديم التحويل للاعتماد.');
    }

    public function approval(StockTransferApprovalRequest $request, StockTransfer $stockTransfer, StockTransferApprovalService $service): RedirectResponse
    {
        $this->authorize('approve', $stockTransfer);
        $request->string('action')->toString() === 'approve'
            ? $service->approve($stockTransfer)
            : $service->reject($stockTransfer, $request->string('reason')->toString());

        return back()->with('success', 'تم تحديث قرار الاعتماد.');
    }

    public function prepare(StockTransferPreparationRequest $request, StockTransfer $stockTransfer, StockTransferPreparationService $service): RedirectResponse
    {
        $this->authorize('prepare', $stockTransfer);
        $service->prepare($stockTransfer, $request->validated('items', []));

        return back()->with('success', 'تم تجهيز التحويل.');
    }

    public function ship(StockTransferShipmentRequest $request, StockTransfer $stockTransfer, StockTransferShipmentService $service): RedirectResponse
    {
        $this->authorize('ship', $stockTransfer);
        $service->ship($stockTransfer, $request->validated('shipping_reference'));

        return back()->with('success', 'تم شحن التحويل إلى مخزون Transit.');
    }

    public function receive(StockTransferReceivingRequest $request, StockTransfer $stockTransfer, StockTransferReceivingService $service): RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);
        $service->receive($stockTransfer, $request->validated('items'));

        return back()->with('success', 'تم تسجيل الاستلام.');
    }

    public function cancel(StockTransferCancellationRequest $request, StockTransfer $stockTransfer, StockTransferCancellationService $service): RedirectResponse
    {
        $this->authorize('cancel', $stockTransfer);
        $service->cancel($stockTransfer, $request->string('reason')->toString());

        return back()->with('success', 'تم إلغاء التحويل وتحرير حجزه.');
    }

    public function reverse(StockTransfer $stockTransfer, StockTransferReversalService $service): RedirectResponse
    {
        $this->authorize('reverse', $stockTransfer);
        $reversal = $service->reverse($stockTransfer);

        return redirect()->route('stock-transfers.show', $reversal)->with('success', 'تم إنشاء وتنفيذ التحويل العكسي.');
    }

    public function discrepancy(StockTransferDiscrepancyRequest $request, StockTransfer $stockTransfer, TransferDiscrepancyService $service): RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);
        $item = StockTransferItem::findOrFail($request->integer('stock_transfer_item_id'));
        $service->report($stockTransfer, $item, $request->validated());

        return back()->with('success', 'تم تسجيل فرق التحويل.');
    }

    public function resolve(Request $request, StockTransferDiscrepancy $discrepancy, TransferDiscrepancyService $service): RedirectResponse
    {
        $this->authorize('resolve', $discrepancy);
        $data = $request->validate(['resolution' => ['required', 'string', 'max:2000']]);
        $service->resolve($discrepancy, $data['resolution']);

        return back()->with('success', 'تم حل فرق التحويل.');
    }

    private function formData(TenantContext $tenant): array
    {
        $companyId = $tenant->companyId();

        return [
            'warehouses' => Warehouse::where('company_id', $companyId)->where('is_active', true)->where('is_system', false)->with('branch')->orderBy('name')->get(),
            'products' => Product::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'rolls' => InventoryRoll::where('company_id', $companyId)->whereIn('status', ['available', 'opened'])->orderBy('roll_number')->get(),
            'scraps' => RollScrap::where('company_id', $companyId)->where('status', 'available')->orderBy('scrap_code')->get(),
        ];
    }
}
