<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrderMaterialIssueRequest;
use App\Http\Requests\WorkOrderMaterialReservationRequest;
use App\Http\Requests\WorkOrderMaterialReturnRequest;
use App\Http\Requests\WorkOrderRollConsumptionRequest;
use App\Http\Requests\WorkOrderScrapConsumptionRequest;
use App\Http\Requests\WorkOrderWasteRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use App\Services\WorkOrderMaterialIssueService;
use App\Services\WorkOrderMaterialReservationService;
use App\Services\WorkOrderMaterialReturnService;
use App\Services\WorkOrderRollConsumptionService;
use App\Services\WorkOrderScrapConsumptionService;
use App\Services\WorkOrderWasteService;
use Illuminate\Http\RedirectResponse;

class WorkOrderMaterialController extends Controller
{
    public function reserve(WorkOrderMaterialReservationRequest $request, WorkOrder $workOrder, WorkOrderMaterialReservationService $service): RedirectResponse
    {
        $this->authorize('update', $workOrder);
        $service->reserve($workOrder);

        return back()->with('success', 'تمت محاولة حجز المواد.');
    }

    public function issue(WorkOrderMaterialIssueRequest $request, WorkOrderMaterial $workOrderMaterial, WorkOrderMaterialIssueService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->issue($workOrderMaterial, (string) $request->validated('quantity'));

        return back()->with('success', 'تم صرف المادة.');
    }

    public function useMaterial(WorkOrderMaterialIssueRequest $request, WorkOrderMaterial $workOrderMaterial, WorkOrderMaterialIssueService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->consume($workOrderMaterial, (string) $request->validated('quantity'), (string) ($request->validated('waste_quantity') ?? 0));

        return back()->with('success', 'تم تسجيل الاستخدام الفعلي.');
    }

    public function consumeRoll(WorkOrderRollConsumptionRequest $request, WorkOrderMaterial $workOrderMaterial, WorkOrderRollConsumptionService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->consume($workOrderMaterial, (string) $request->validated('length'), (string) $request->validated('usable_area'), (string) ($request->validated('waste_area') ?? 0));

        return back()->with('success', 'تم تسجيل استهلاك الرول.');
    }

    public function consumeScrap(WorkOrderScrapConsumptionRequest $request, WorkOrderMaterial $workOrderMaterial, WorkOrderScrapConsumptionService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->consume($workOrderMaterial);

        return back()->with('success', 'تم استهلاك القصاصة.');
    }

    public function returnMaterial(WorkOrderMaterialReturnRequest $request, WorkOrderMaterial $workOrderMaterial, WorkOrderMaterialReturnService $service): RedirectResponse
    {
        $this->authorize('manage', $workOrderMaterial);
        $service->return($workOrderMaterial, (string) $request->validated('quantity'));

        return back()->with('success', 'تم إرجاع المادة.');
    }

    public function waste(WorkOrderWasteRequest $request, WorkOrder $workOrder, WorkOrderWasteService $service): RedirectResponse
    {
        $this->authorize('update', $workOrder);
        $service->record($workOrder, $request->validated());

        return back()->with('success', 'تم تسجيل الهدر.');
    }
}
