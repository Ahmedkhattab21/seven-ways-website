<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrderActionRequest;
use App\Http\Requests\WorkOrderTechnicianRequest;
use App\Models\Employee;
use App\Models\WorkOrder;
use App\Models\WorkOrderService;
use App\Services\WorkOrderCancellationService;
use App\Services\WorkOrderServiceActionService;
use App\Services\WorkOrderTechnicianService;
use Illuminate\Http\RedirectResponse;

class WorkOrderActionController extends Controller
{
    public function assign(WorkOrderTechnicianRequest $request, WorkOrderService $workOrderService, WorkOrderTechnicianService $service): RedirectResponse
    {
        $this->authorize('view', $workOrderService);
        $service->assign($workOrderService, Employee::findOrFail($request->integer('employee_id')), $request->validated());

        return back()->with('success', 'تم تعيين الفني.');
    }

    public function action(WorkOrderActionRequest $request, WorkOrderService $workOrderService, string $action, WorkOrderServiceActionService $service): RedirectResponse
    {
        $this->authorize('act', $workOrderService);
        $permission = match ($action) {
            'pause' => 'work_orders.pause', 'complete' => 'work_orders.complete',
            'reopen' => 'work_orders.reopen', default => 'work_orders.start',
        };
        abort_unless($request->user()->hasPermission($permission), 403);
        $employee = $request->filled('employee_id')
            ? Employee::findOrFail($request->integer('employee_id'))
            : Employee::where('user_id', $request->user()->id)->firstOrFail();
        match ($action) {
            'start' => $service->start($workOrderService, $employee, $request->boolean('materials_override')),
            'pause' => $service->pause($workOrderService, $employee, $request->input('reason')),
            'resume' => $service->resume($workOrderService, $employee),
            'complete' => $service->complete($workOrderService),
            'reopen' => $service->reopen($workOrderService, (string) $request->input('reason')),
            default => abort(404),
        };

        return back()->with('success', 'تم تحديث حالة الخدمة.');
    }

    public function cancel(WorkOrderActionRequest $request, WorkOrder $workOrder, WorkOrderCancellationService $service): RedirectResponse
    {
        $this->authorize('cancel', $workOrder);
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel($workOrder, $request->input('reason'));

        return back()->with('success', 'تم إلغاء أمر العمل.');
    }
}
