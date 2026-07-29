<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\ServicePackageRequest;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServicePrice;
use App\Models\VehicleSize;
use App\Services\ServicePackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicePackageController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $branchId = $request->integer('branch_id') ?: $tenant->branchId();
        $today = now()->toDateString();
        $packages = ServicePackage::where('company_id', $tenant->companyId())
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('code', 'like', '%'.$request->search.'%')))
            ->when($request->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->withCount('items')
            ->with([
                'items.service:id,name,default_duration_minutes',
                'branchPrices' => fn ($query) => $query->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->with(['branch:id,name', 'vehicleSize:id,name'])->where('is_available', true)
                    ->whereDate('effective_from', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                    ->orderByDesc('effective_from'),
            ])->latest()->paginate(20)->withQueryString();

        $this->appendPackageMetrics($packages->getCollection(), $branchId, $today);

        return view('services.packages.index', [
            'packages' => $packages,
            'branches' => $tenant->accessibleBranches(),
            'currentBranchId' => $branchId,
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        return view('services.packages.form', ['servicePackage' => new ServicePackage] + $this->references($tenant));
    }

    public function store(ServicePackageRequest $request, ServicePackageService $packages): RedirectResponse
    {
        $items = collect($request->validated('items'))->values()->map(
            fn ($item, $index) => $item + ['is_required' => true, 'sort_order' => $index]
        )->all();
        $price = $request->filled('price') ? $request->safe()->only([
            'branch_id', 'vehicle_size_id', 'price', 'minimum_price',
            'effective_from', 'effective_to', 'is_available',
        ]) : null;
        if ($price !== null && ! $request->user()->hasPermission('service_packages.manage_prices')) {
            abort(403);
        }
        $package = $packages->save(
            $request->safe()->except('items', 'service_ids', 'branch_id', 'vehicle_size_id', 'price',
                'minimum_price', 'effective_from', 'effective_to', 'is_available'),
            $items,
            null,
            $price
        );

        return redirect()->route('service-packages.edit', $package)->with('success', 'تم إنشاء باقة الخدمات.');
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
        $items = collect($request->validated('items'))->values()->map(
            fn ($item, $index) => $item + ['is_required' => true, 'sort_order' => $index]
        )->all();
        $packages->save(
            $request->safe()->except('items', 'service_ids', 'branch_id', 'vehicle_size_id', 'price',
                'minimum_price', 'effective_from', 'effective_to', 'is_available'),
            $items,
            $servicePackage
        );

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
        ServicePackageService $packages
    ): RedirectResponse {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'], 'vehicle_size_id' => ['nullable', 'integer'],
            'price' => ['required', 'numeric', 'min:0'], 'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'is_available' => ['sometimes', 'boolean'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $packages->savePrice($servicePackage, $data);

        return back()->with('success', 'تم حفظ سعر الباقة للفرع.');
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'services' => Service::where('company_id', $tenant->companyId())->where('is_active', true)
                ->with(['branchServices:id,branch_id,service_id,default_price', 'prices' => fn ($query) => $query
                    ->where('is_active', true)->whereNull('vehicle_size_id')->whereNull('vehicle_type_id')
                    ->whereDate('effective_from', '<=', now()->toDateString())
                    ->where(fn ($query) => $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', now()->toDateString()))])
                ->orderBy('name')->get(),
            'branches' => $tenant->accessibleBranches(),
            'vehicleSizes' => VehicleSize::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }

    private function appendPackageMetrics($packages, ?int $branchId, string $today): void
    {
        if (! $branchId || $packages->isEmpty()) {
            return;
        }
        $serviceIds = $packages->flatMap->items->pluck('service_id')->unique();
        $prices = ServicePrice::query()->whereIn('service_id', $serviceIds)->where('branch_id', $branchId)
            ->where('is_active', true)->whereNull('vehicle_size_id')->whereNull('vehicle_type_id')
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByDesc('priority')->orderByDesc('effective_from')->get()->unique('service_id')->keyBy('service_id');
        $defaults = Service::query()->whereIn('id', $serviceIds)->with(['branchServices' => fn ($query) => $query
            ->where('branch_id', $branchId)])->get()->keyBy('id');

        $packages->each(function (ServicePackage $package) use ($prices, $defaults) {
            $missingPrice = false;
            $total = $package->items->reduce(function ($sum, $item) use ($prices, $defaults, &$missingPrice) {
                $price = $prices->get($item->service_id)?->price
                    ?? $defaults->get($item->service_id)?->branchServices->first()?->default_price;
                if ($price === null) {
                    $missingPrice = true;
                }

                return $price === null ? $sum : $sum + ((float) $price * (float) $item->quantity);
            }, 0.0);
            $package->setAttribute('standalone_total', $missingPrice ? null : $total);
            $package->setAttribute('total_duration_minutes', $package->items->sum(
                fn ($item) => (int) ($item->service?->default_duration_minutes ?? 0) * (float) $item->quantity
            ));
        });
    }
}
