<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleInspectionRequest;
use App\Models\VehicleInspection;
use App\Services\AttachmentService;
use App\Services\VehicleInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleInspectionController extends Controller
{
    public function show(VehicleInspection $vehicleInspection): View
    {
        $this->authorize('view', $vehicleInspection);

        return view('work-orders.inspection', ['inspection' => $vehicleInspection->load(['items', 'attachments', 'workOrder.vehicle'])]);
    }

    public function update(VehicleInspectionRequest $request, VehicleInspection $vehicleInspection, VehicleInspectionService $service): RedirectResponse
    {
        $this->authorize('update', $vehicleInspection);
        $service->save($vehicleInspection, $request->safe()->except('items'), $request->validated('items'));

        return back()->with('success', 'تم حفظ الفحص.');
    }

    public function complete(Request $request, VehicleInspection $vehicleInspection, VehicleInspectionService $service): RedirectResponse
    {
        $this->authorize('complete', $vehicleInspection);
        $service->complete($vehicleInspection, $request->validate(['customer_name' => ['nullable', 'string', 'max:255']])['customer_name'] ?? null);

        return redirect()->route('work-orders.show', $vehicleInspection->work_order_id)->with('success', 'تم إكمال الفحص.');
    }

    public function photo(Request $request, VehicleInspection $vehicleInspection, AttachmentService $attachments): RedirectResponse
    {
        $this->authorize('update', $vehicleInspection);
        abort_unless($request->user()->hasPermission('vehicle_inspections.manage_photos'), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
            'category' => ['required', 'in:inspection_overview,inspection_damage,inspection_odometer,inspection_interior,inspection_signature'],
        ]);
        $attachments->store($vehicleInspection, $request->file('file'), $data['category']);

        return back()->with('success', 'تم رفع صورة الفحص بشكل خاص.');
    }
}
