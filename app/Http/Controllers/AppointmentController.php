<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\AppointmentService;
use App\Services\WorkOrderWarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $appointments = $this->query($request, $tenant)->paginate(30)->withQueryString();

        return view('appointments.index', ['appointments' => $appointments, 'branches' => $tenant->accessibleBranches()]);
    }

    public function calendar(Request $request, TenantContext $tenant): View
    {
        $from = $request->filled('from')
            ? $request->date('from')->startOfDay()
            : today()->startOfMonth();
        $to = $request->filled('to')
            ? $request->date('to')->endOfDay()
            : today()->endOfMonth();

        $appointments = $this->query($request, $tenant)
            ->whereBetween('scheduled_start', [$from, $to])
            ->get();

        return view('appointments.calendar', ['appointments' => $appointments, 'branches' => $tenant->accessibleBranches()]);
    }

    public function create(TenantContext $tenant): View
    {
        return view('appointments.form', ['appointment' => new Appointment] + $this->references($tenant));
    }

    public function store(AppointmentRequest $request, AppointmentService $service): RedirectResponse
    {
        $appointment = $service->save($request->safe()->except('services'), $request->validated('services'));

        return redirect()->route('appointments.show', $appointment)->with('success', 'تم إنشاء الحجز.');
    }

    public function show(
        Appointment $appointment,
        TenantContext $tenant,
        WorkOrderWarehouseService $workOrderWarehouses
    ): View {
        $this->authorize('view', $appointment);
        $appointment->load(['branch', 'customer', 'vehicle', 'quotation', 'assignedEmployee', 'services.service', 'deposits.paymentMethod']);

        $activeWorkOrder = WorkOrder::query()
            ->where('appointment_id', $appointment->id)
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->latest('id')
            ->first();
        $defaultWorkOrderWarehouse = $workOrderWarehouses->defaultFor($appointment->branch);

        return view('appointments.show', compact(
            'appointment',
            'activeWorkOrder',
            'defaultWorkOrderWarehouse'
        ) + $this->references($tenant));
    }

    public function edit(Appointment $appointment, TenantContext $tenant): View
    {
        $this->authorize('update', $appointment);
        $appointment->load('services');

        return view('appointments.form', ['appointment' => $appointment] + $this->references($tenant));
    }

    public function update(AppointmentRequest $request, Appointment $appointment, AppointmentService $service): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $service->save($request->safe()->except('services'), $request->validated('services'), $appointment);

        return redirect()->route('appointments.show', $appointment)->with('success', 'تم تحديث الحجز.');
    }

    private function query(Request $request, TenantContext $tenant)
    {
        return Appointment::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->with(['branch', 'customer', 'vehicle', 'assignedEmployee', 'services'])
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('assigned_employee_id', $request->employee_id))
            ->when($request->filled('service_id'), fn ($query) => $query->whereHas('services', fn ($query) => $query->where('service_id', $request->service_id)))
            ->orderBy('scheduled_start');
    }

    private function references(TenantContext $tenant): array
    {
        $branches = $tenant->accessibleBranches();
        $branchIds = $branches->pluck('id');
        $availableAtBranch = fn ($query) => $query
            ->whereIn('branch_id', $branchIds)
            ->where('is_available', true)
            ->where('is_active', true);

        return [
            'branches' => $branches,
            'customers' => Customer::where('company_id', $tenant->companyId())->where('status', 'active')->orderBy('name')->get(),
            'vehicles' => Vehicle::where('company_id', $tenant->companyId())->where('status', 'active')->latest()->get(),
            'employees' => Employee::where('company_id', $tenant->companyId())->where('status', 'active')->orderBy('name')->get(),
            'services' => Service::where('company_id', $tenant->companyId())
                ->where('is_active', true)
                ->whereHas('branchServices', $availableAtBranch)
                ->with(['branchServices' => $availableAtBranch])
                ->orderBy('name')
                ->get(),
            'paymentMethods' => PaymentMethod::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
}
