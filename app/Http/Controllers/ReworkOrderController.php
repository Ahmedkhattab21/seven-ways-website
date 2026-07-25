<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ReworkActionRequest;
use App\Models\Product;
use App\Models\ReworkOrder;
use App\Models\Warehouse;
use App\Models\WorkOrderMaterial;
use App\Services\AttachmentService;
use App\Services\ReworkExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReworkOrderController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', ReworkOrder::class);

        return view('rework.index', [
            'reworks' => ReworkOrder::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->with('workOrder')->latest()->paginate(30),
        ]);
    }

    public function show(ReworkOrder $reworkOrder): View
    {
        $this->authorize('view', $reworkOrder);

        return view('rework.show', [
            'rework' => $reworkOrder->load([
                'workOrder.customer', 'workOrder.vehicle', 'qualityCheck', 'warrantyClaim',
                'services.workOrderService', 'materials.product', 'attachments',
            ]),
            'products' => Product::where('company_id', $reworkOrder->company_id)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('company_id', $reworkOrder->company_id)
                ->where('branch_id', $reworkOrder->branch_id)->where('is_active', true)
                ->where('is_system', false)->where('allows_work_order_issue', true)->get(),
        ]);
    }

    public function action(
        ReworkActionRequest $request,
        ReworkOrder $reworkOrder,
        string $action,
        ReworkExecutionService $service
    ): RedirectResponse {
        $this->authorize($action === 'service-complete' ? 'complete' : $action, $reworkOrder);
        match ($action) {
            'approve' => $service->approve($reworkOrder),
            'start' => $service->start($reworkOrder),
            'service-complete' => $service->completeService($reworkOrder, (int) $request->validated('rework_service_id')),
            'complete' => $service->complete($reworkOrder),
        };

        return back()->with('success', 'Rework status updated.');
    }

    public function photo(Request $request, ReworkOrder $reworkOrder, AttachmentService $attachments): RedirectResponse
    {
        $this->authorize('complete', $reworkOrder);
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
            'category' => ['required', 'in:rework_before,rework_during,rework_after'],
        ]);
        $attachments->store($reworkOrder, $request->file('file'), $request->input('category'));

        return back()->with('success', 'Private rework photo uploaded.');
    }

    public function material(Request $request, ReworkOrder $reworkOrder, ReworkExecutionService $service): RedirectResponse
    {
        $this->authorize('view', $reworkOrder);
        abort_unless($request->user()->hasPermission('work_order_materials.reserve'), 403);
        $data = $request->validate([
            'work_order_service_id' => ['required', 'integer', 'exists:work_order_services,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'material_type' => ['required', 'in:quantity,roll,scrap'],
            'expected_quantity' => ['required', 'numeric', 'gt:0'],
            'roll_id' => ['nullable', 'integer', 'exists:inventory_rolls,id'],
            'scrap_id' => ['nullable', 'integer', 'exists:roll_scraps,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $service->addMaterial($reworkOrder, $data);

        return back()->with('success', 'Rework material added without deducting stock.');
    }

    public function reserveMaterial(WorkOrderMaterial $workOrderMaterial, ReworkExecutionService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->reserveMaterial($workOrderMaterial);

        return back()->with('success', 'Rework material reserved.');
    }
}
