<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\DeliveryInspectionRequest;
use App\Http\Requests\WorkOrderDeliveryRequest;
use App\Models\WorkOrder;
use App\Services\AttachmentService;
use App\Services\DeliveryInspectionService;
use App\Services\VehicleInspectionService;
use App\Services\WorkOrderDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        return view('deliveries.index', [
            'orders' => WorkOrder::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('status', 'ready_for_delivery')->with(['customer', 'vehicle'])->latest()->paginate(30),
        ]);
    }

    public function show(WorkOrder $workOrder, DeliveryInspectionService $service): View
    {
        $this->authorize('view', $workOrder);
        abort_unless(request()->user()->hasPermission('vehicle_inspections.delivery'), 403);
        $inspection = $service->create($workOrder);

        return view('deliveries.show', ['workOrder' => $workOrder->load(['customer', 'vehicle']), 'inspection' => $inspection->load(['items', 'attachments'])]);
    }

    public function update(
        DeliveryInspectionRequest $request,
        WorkOrder $workOrder,
        DeliveryInspectionService $delivery,
        VehicleInspectionService $inspections
    ): RedirectResponse {
        $inspection = $delivery->create($workOrder);
        $this->authorize('update', $inspection);
        $inspections->save($inspection, $request->safe()->except('items'), $request->validated('items'));

        return back()->with('success', 'Delivery inspection saved.');
    }

    public function complete(Request $request, WorkOrder $workOrder, DeliveryInspectionService $delivery, VehicleInspectionService $inspections): RedirectResponse
    {
        $inspection = $delivery->create($workOrder);
        $this->authorize('complete', $inspection);
        $inspections->complete($inspection, $request->validate(['receiver_name' => ['required', 'string', 'max:255']])['receiver_name']);

        return back()->with('success', 'Delivery inspection completed.');
    }

    public function photo(Request $request, WorkOrder $workOrder, DeliveryInspectionService $delivery, AttachmentService $attachments): RedirectResponse
    {
        $inspection = $delivery->create($workOrder);
        $this->authorize('update', $inspection);
        abort_unless($request->user()->hasPermission('vehicle_inspections.delivery_photos'), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
            'category' => ['required', 'in:delivery_overview,delivery_signature'],
        ]);
        $attachments->store($inspection, $request->file('file'), $data['category']);

        return back()->with('success', 'Private delivery file uploaded.');
    }

    public function deliver(WorkOrderDeliveryRequest $request, WorkOrder $workOrder, WorkOrderDeliveryService $service): RedirectResponse
    {
        $this->authorize('view', $workOrder);
        $service->deliver($workOrder, $request->validated());

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Vehicle delivered.');
    }
}
