<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\VehicleRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Services\VehicleOwnershipService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $vehicles = Vehicle::query()->forUser($request->user())
            ->with(['customer', 'brand', 'model'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(fn ($inner) => $inner->where('plate_number', 'like', "%{$search}%")
                    ->orWhere('vin', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%")));
            })
            ->latest()->paginate(20)->withQueryString();

        return view('vehicles.index', compact('vehicles'));
    }

    public function create(Request $request, TenantContext $tenant): View
    {
        $options = $this->options($tenant);
        $vehicle = new Vehicle();
        $customerId = $request->integer('customer_id');
        if ($customerId && $options['customers']->contains('id', $customerId)) {
            $vehicle->customer_id = $customerId;
        }

        return view('vehicles.form', ['vehicle' => $vehicle, ...$options]);
    }

    public function store(VehicleRequest $request, VehicleService $service): RedirectResponse
    {
        $vehicle = $service->save(new Vehicle(), $request->validated());

        return redirect()->route('vehicles.show', $vehicle)->with('status', 'تمت إضافة السيارة.');
    }

    public function show(Vehicle $vehicle, TenantContext $tenant): View
    {
        $this->authorize('view', $vehicle);
        $vehicle->load(['customer', 'brand', 'model', 'type', 'size', 'ownershipHistory.fromCustomer', 'ownershipHistory.toCustomer', 'attachments']);
        $customers = Customer::query()->forUser($tenant->user())
            ->where('status', 'active')->whereKeyNot($vehicle->customer_id)->orderBy('name')->get();

        return view('vehicles.show', compact('vehicle', 'customers'));
    }

    public function edit(Vehicle $vehicle, TenantContext $tenant): View
    {
        $this->authorize('update', $vehicle);

        return view('vehicles.form', ['vehicle' => $vehicle, ...$this->options($tenant)]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle, VehicleService $service): RedirectResponse
    {
        $service->save($vehicle, $request->validated());

        return redirect()->route('vehicles.show', $vehicle)->with('status', 'تم تحديث السيارة.');
    }

    public function transfer(Request $request, Vehicle $vehicle, VehicleOwnershipService $service): RedirectResponse
    {
        $this->authorize('transfer', $vehicle);
        $data = $request->validate([
            'customer_id' => ['required', 'integer'], 'transferred_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->transfer($vehicle, (int) $data['customer_id'], $data);

        return back()->with('status', 'تم نقل ملكية السيارة.');
    }

    private function options(TenantContext $tenant): array
    {
        return [
            'customers' => Customer::query()->forUser($tenant->user())->where('status', 'active')->orderBy('name')->get(),
            'brands' => VehicleBrand::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'models' => VehicleModel::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'types' => VehicleType::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->get(),
            'sizes' => VehicleSize::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))->get(),
        ];
    }
}
