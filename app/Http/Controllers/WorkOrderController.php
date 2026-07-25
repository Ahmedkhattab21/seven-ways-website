<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\WorkOrderRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\WorkOrderCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $orders = WorkOrder::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->with(['branch', 'customer', 'vehicle'])
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->latest()->paginate(30)->withQueryString();

        return view('work-orders.index', ['orders' => $orders, 'branches' => $tenant->accessibleBranches()]);
    }

    public function create(TenantContext $tenant): View
    {
        $branches = $tenant->accessibleBranches();

        return view('work-orders.form', [
            'branches' => $branches,
            'warehouses' => Warehouse::whereIn('branch_id', $branches->pluck('id'))->where('is_active', true)->where('is_system', false)->where('allows_work_order_issue', true)->get(),
            'appointments' => Appointment::whereIn('branch_id', $branches->pluck('id'))->where('status', 'checked_in')->get(),
            'customers' => Customer::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'vehicles' => Vehicle::where('company_id', $tenant->companyId())->where('status', 'active')->get(),
            'services' => Service::where('company_id', $tenant->companyId())->where('is_active', true)->get(),
        ]);
    }

    public function store(WorkOrderRequest $request, WorkOrderCreationService $creator): RedirectResponse
    {
        $data = $request->validated();
        $order = match ($data['source']) {
            'appointment' => $creator->fromAppointment(Appointment::findOrFail($data['appointment_id']), $data['warehouse_id']),
            'quotation' => $creator->fromQuotation(\App\Models\Quotation::findOrFail($data['quotation_id']), $data['warehouse_id']),
            default => $creator->direct(collect($data)->except(['source', 'services'])->all(), $data['services']),
        };

        return redirect()->route('work-orders.show', $order)->with('success', 'تم إنشاء أمر العمل.');
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);
        $workOrder->load([
            'branch', 'warehouse', 'customer', 'vehicle', 'inspection.items', 'inspection.attachments',
            'services.technicians.employee', 'services.materials.product', 'wastes', 'statusLogs',
            'qualityChecks', 'reworkOrders', 'deliveryInspection',
        ]);

        return view('work-orders.show', [
            'workOrder' => $workOrder,
            'employees' => Employee::where('company_id', $workOrder->company_id)->where('branch_id', $workOrder->branch_id)->where('status', 'active')->get(),
        ]);
    }
}
