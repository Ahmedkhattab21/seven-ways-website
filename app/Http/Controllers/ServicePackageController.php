<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ServicePackageRequest;
use App\Models\Branch;
use App\Models\BranchServicePackage;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\VehicleSize;
use App\Services\AuditService;
use App\Services\ServicePackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicePackageController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $packages = ServicePackage::where('company_id', $tenant->companyId())->withCount('items')
            ->with('branchPrices.branch')->latest()->paginate(20);

        return view('services.packages.index', compact('packages'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('services.packages.form', ['servicePackage' => new ServicePackage] + $this->references($tenant));
    }

    public function store(ServicePackageRequest $request, ServicePackageService $packages): RedirectResponse
    {
        $items = collect($request->validated('service_ids'))->values()->map(
            fn ($serviceId, $index) => ['service_id' => $serviceId, 'quantity' => 1, 'is_required' => true, 'sort_order' => $index]
        )->all();
        $package = $packages->save($request->safe()->except('service_ids'), $items);

        return redirect()->route('service-packages.edit', $package)->with('success', 'تم إنشاء الباقة.');
    }

    public function edit(ServicePackage $servicePackage, TenantContext $tenant): View
    {
        $this->authorize('update', $servicePackage);
        $servicePackage->load(['items.service', 'branchPrices.branch', 'branchPrices.vehicleSize']);

        return view('services.packages.form', compact('servicePackage') + $this->references($tenant));
    }

    public function update(
        ServicePackageRequest $request,
        ServicePackage $servicePackage,
        ServicePackageService $packages
    ): RedirectResponse {
        $this->authorize('update', $servicePackage);
        $items = collect($request->validated('service_ids'))->values()->map(
            fn ($serviceId, $index) => ['service_id' => $serviceId, 'quantity' => 1, 'is_required' => true, 'sort_order' => $index]
        )->all();
        $packages->save($request->safe()->except('service_ids'), $items, $servicePackage);

        return back()->with('success', 'تم تحديث الباقة.');
    }

    public function disable(ServicePackage $servicePackage, ServicePackageService $packages): RedirectResponse
    {
        $this->authorize('disable', $servicePackage);
        $packages->disable($servicePackage);

        return back()->with('success', 'تم تعطيل الباقة.');
    }

    public function saveBranchPrice(
        Request $request,
        ServicePackage $servicePackage,
        TenantContext $tenant,
        AuditService $audit
    ): RedirectResponse {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'], 'vehicle_size_id' => ['nullable', 'integer'],
            'price' => ['required', 'numeric', 'min:0'], 'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'is_available' => ['sometimes', 'boolean'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id', $tenant->companyId())->firstOrFail();
        if (! $branch->is_active || ! $tenant->user()?->canAccessBranch($branch)
            || $servicePackage->company_id !== $tenant->companyId()) {
            throw new BusinessRuleException('Package price is outside your branch scope.', status: 403);
        }
        if (! empty($data['vehicle_size_id'])) {
            VehicleSize::query()->whereKey($data['vehicle_size_id'])
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->firstOrFail();
        }
        $overlap = BranchServicePackage::query()
            ->where('branch_id', $branch->id)->where('service_package_id', $servicePackage->id)
            ->where('vehicle_size_id', $data['vehicle_size_id'] ?? null)->where('is_available', true)
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $data['effective_from']))
            ->exists();
        if ($overlap) {
            throw new BusinessRuleException('An overlapping available package price already exists.');
        }
        $price = new BranchServicePackage(collect($data)->except('branch_id')->all());
        $price->forceFill(['branch_id' => $branch->id, 'service_package_id' => $servicePackage->id])->save();
        $audit->record('service_package_price.saved', $price, ['package_id' => $servicePackage->id]);

        return back()->with('success', 'تم حفظ سعر الباقة للفرع.');
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'services' => Service::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'branches' => $tenant->accessibleBranches(),
            'vehicleSizes' => VehicleSize::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
}
