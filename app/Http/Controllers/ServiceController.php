<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BranchServiceRequest;
use App\Http\Requests\EmployeeServiceSkillRequest;
use App\Http\Requests\ServiceCommissionRuleRequest;
use App\Http\Requests\ServiceMaterialRequirementRequest;
use App\Http\Requests\ServicePriceRequest;
use App\Http\Requests\ServiceRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceCommissionRule;
use App\Models\ServiceMaterialRequirement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Services\AuditService;
use App\Services\BranchServiceAvailabilityService;
use App\Services\EmployeeServiceSkillService;
use App\Services\ServiceCatalogService;
use App\Services\ServiceCostEstimator;
use App\Services\ServiceMaterialRequirementService;
use App\Services\ServicePricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $branchId = $request->integer('branch_id') ?: $tenant->branchId();
        $today = now()->toDateString();
        $services = Service::query()->where('company_id', $tenant->companyId())->with([
            'category',
            'branchServices' => fn ($query) => $query->when($branchId, fn ($query) => $query->where('branch_id', $branchId)),
            'prices' => fn ($query) => $query->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereNull('vehicle_size_id')->whereNull('vehicle_type_id')->where('is_active', true)
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                ->orderByDesc('priority')->orderByDesc('effective_from'),
        ])->withCount('materialRequirements')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('code', 'like', '%'.$request->search.'%')))
            ->when($request->filled('service_category_id'), fn ($q) => $q->where('service_category_id', $request->service_category_id))
            ->when($request->filled('service_type'), fn ($q) => $q->where('service_type', $request->service_type))
            ->when($request->filled('pricing_type'), fn ($q) => $q->where('pricing_type', $request->pricing_type))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('branchServices', fn ($q) => $q
                ->where('branch_id', $request->branch_id)->where('is_available', true)))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('requires_inspection'), fn ($q) => $q->where('requires_inspection', $request->boolean('requires_inspection')))
            ->when($request->filled('requires_quality_check'), fn ($q) => $q->where('requires_quality_check', $request->boolean('requires_quality_check')))
            ->latest()->paginate(20)->withQueryString();

        return view('services.index', [
            'services' => $services,
            'categories' => ServiceCategory::where('company_id', $tenant->companyId())->orderBy('name')->get(),
            'branches' => $tenant->accessibleBranches(),
            'currentBranchId' => $branchId,
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        return view('services.form', $this->references($tenant) + ['service' => new Service]);
    }

    public function store(ServiceRequest $request, ServiceCatalogService $catalog): RedirectResponse
    {
        $service = $catalog->saveService($request->validated());

        return redirect()->route('services.show', $service)->with('success', 'تم إنشاء الخدمة.');
    }

    public function show(Service $service, TenantContext $tenant): View
    {
        $this->authorize('view', $service);
        $service->load([
            'category', 'defaultTax', 'branchServices.branch', 'prices.vehicleSize', 'prices.vehicleType',
            'materialRequirements.product', 'materialRequirements.unit', 'materialRequirements.substitutes.substituteProduct',
            'rollProfiles.filmProduct', 'skills.employee', 'commissionRules.branch',
        ]);

        return view('services.show', $this->references($tenant) + [
            'service' => $service,
            'branches' => $tenant->accessibleBranches(),
            'products' => Product::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::where('company_id', $tenant->companyId())->where('status', 'active')->orderBy('name')->get(),
            'roles' => Role::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('display_name')->get(),
            'canViewCost' => auth()->user()->can('viewCost', $service),
        ]);
    }

    public function edit(Service $service, TenantContext $tenant): View
    {
        $this->authorize('update', $service);

        return view('services.form', $this->references($tenant) + compact('service'));
    }

    public function update(ServiceRequest $request, Service $service, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->authorize('update', $service);
        $catalog->saveService($request->validated(), $service);

        return redirect()->route('services.show', $service)->with('success', 'تم تحديث الخدمة.');
    }

    public function disable(Service $service, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->authorize('disable', $service);
        $catalog->disable($service);

        return back()->with('success', 'تم تعطيل الخدمة بدون حذفها.');
    }

    public function saveAvailability(
        BranchServiceRequest $request,
        Service $service,
        BranchServiceAvailabilityService $availability
    ): RedirectResponse {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $availability->save($service, $branch, $request->safe()->except('branch_id'));

        return back()->with('success', 'تم تحديث توافر الخدمة بالفرع.');
    }

    public function savePrice(
        ServicePriceRequest $request,
        Service $service,
        ServicePricingService $pricing
    ): RedirectResponse {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $pricing->save($service, $branch, $request->safe()->except('branch_id'));

        return back()->with('success', 'تم حفظ سعر الخدمة.');
    }

    public function saveMaterial(
        ServiceMaterialRequirementRequest $request,
        Service $service,
        ServiceMaterialRequirementService $materials
    ): RedirectResponse {
        $materials->save($service, $request->validated());

        return back()->with('success', 'تم حفظ المادة المتوقعة بدون أي تأثير مخزني.');
    }

    public function saveSubstitute(
        Request $request,
        Service $service,
        ServiceMaterialRequirementService $materials
    ): RedirectResponse {
        $data = $request->validate([
            'service_material_requirement_id' => ['required', 'integer'],
            'substitute_product_id' => ['required', 'integer'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);
        $requirement = ServiceMaterialRequirement::query()->whereKey($data['service_material_requirement_id'])
            ->where('service_id', $service->id)->firstOrFail();
        $product = Product::query()->findOrFail($data['substitute_product_id']);
        $materials->saveSubstitute($requirement, $product, (string) $data['conversion_factor'], (int) ($data['priority'] ?? 0));

        return back()->with('success', 'تم حفظ بديل المادة للاستخدام المستقبلي فقط.');
    }

    public function saveRollProfile(Request $request, Service $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_size_id' => ['nullable', 'integer'], 'vehicle_type_id' => ['nullable', 'integer'],
            'film_product_id' => ['nullable', 'integer'],
            'coverage_type' => ['required', Rule::in([
                'full_vehicle', 'front_package', 'hood', 'bumper', 'fenders', 'doors', 'roof',
                'trunk', 'headlights', 'windshield', 'side_windows', 'rear_window', 'interior_screen', 'custom',
            ])],
            'expected_width' => ['nullable', 'numeric', 'gt:0'], 'expected_length' => ['nullable', 'numeric', 'gt:0'],
            'expected_area' => ['required', 'numeric', 'gt:0'],
            'expected_waste_percentage' => ['required', 'numeric', 'between:0,100'],
            'minimum_scrap_width' => ['nullable', 'numeric', 'gt:0'], 'minimum_scrap_length' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($service->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        if (! empty($data['film_product_id'])) {
            Product::query()->whereKey($data['film_product_id'])->where('company_id', $service->company_id)
                ->where('tracking_type', 'roll')->where('is_active', true)->firstOrFail();
        }
        foreach (['vehicle_size_id' => VehicleSize::class, 'vehicle_type_id' => VehicleType::class] as $key => $model) {
            if (! empty($data[$key])) {
                $model::query()->whereKey($data[$key])
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $service->company_id))
                    ->where('is_active', true)->firstOrFail();
            }
        }
        $profile = $service->rollProfiles()->create($data);
        $audit->record('service_roll_profile.saved', $profile, ['service_id' => $service->id]);

        return back()->with('success', 'تم حفظ تقدير استهلاك الرول دون اختيار رول فعلي.');
    }

    public function saveSkill(
        EmployeeServiceSkillRequest $request,
        Service $service,
        EmployeeServiceSkillService $skills
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $skills->save($employee, $service, $request->safe()->except('employee_id'));

        return back()->with('success', 'تم تحديث مهارة الفني.');
    }

    public function saveCommission(
        ServiceCommissionRuleRequest $request,
        Service $service,
        TenantContext $tenant,
        AuditService $audit
    ): RedirectResponse {
        $data = $request->validated();
        if ($service->company_id !== $tenant->companyId()) {
            abort(403);
        }
        foreach (['branch_id' => Branch::class, 'employee_id' => Employee::class, 'role_id' => Role::class] as $key => $model) {
            if (! empty($data[$key]) && ! $model::query()->whereKey($data[$key])->where('company_id', $tenant->companyId())->exists()) {
                throw new BusinessRuleException('Commission rule scope is outside the current company.', status: 403);
            }
        }
        if (! empty($data['employee_id']) && ! empty($data['branch_id'])
            && ! Employee::query()->whereKey($data['employee_id'])->where('branch_id', $data['branch_id'])->exists()) {
            throw new BusinessRuleException('The employee does not belong to the selected branch.');
        }
        $overlap = ServiceCommissionRule::query()
            ->where('company_id', $tenant->companyId())->where('service_id', $service->id)
            ->where('branch_id', $data['branch_id'] ?? null)->where('employee_id', $data['employee_id'] ?? null)
            ->where('role_id', $data['role_id'] ?? null)->where('priority', $data['priority'] ?? 0)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $data['effective_from']))
            ->exists();
        if ($overlap) {
            throw new BusinessRuleException('An overlapping active commission rule with the same scope already exists.');
        }
        $rule = DB::transaction(function () use ($data, $service, $audit) {
            $rule = new ServiceCommissionRule($data);
            $rule->forceFill(['company_id' => $service->company_id, 'service_id' => $service->id])->save();
            $audit->record('service_commission_rule.saved', $rule, ['service_id' => $service->id]);

            return $rule;
        });

        return back()->with('success', "تم حفظ قاعدة العمولة #{$rule->id} دون إنشاء مستحق مالي.");
    }

    public function estimate(Request $request, Service $service, ServiceCostEstimator $estimator): View
    {
        $this->authorize('view', $service);
        $data = $request->validate([
            'branch_id' => ['required', 'integer'], 'vehicle_size_id' => ['nullable', 'integer'],
            'vehicle_type_id' => ['nullable', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $result = $estimator->estimate(
            $service,
            Branch::query()->findOrFail($data['branch_id']),
            ! empty($data['vehicle_size_id']) ? VehicleSize::query()->findOrFail($data['vehicle_size_id']) : null,
            ! empty($data['vehicle_type_id']) ? VehicleType::query()->findOrFail($data['vehicle_type_id']) : null,
            $data['quantity']
        );
        if (! auth()->user()->can('viewCost', $service)) {
            unset($result['estimated_material_cost'], $result['estimated_waste_cost'], $result['estimated_total_cost'], $result['estimated_margin']);
            $result['materials'] = $result['materials']->map(
                fn ($item) => collect($item)->except(['estimated_cost', 'estimated_waste_cost'])->all()
            );
            $result['roll_profiles'] = $result['roll_profiles']->map(
                fn ($item) => collect($item)->except(['estimated_cost', 'estimated_waste_cost'])->all()
            );
        }

        return view('services.estimate', compact('service', 'result'));
    }

    private function references(TenantContext $tenant): array
    {
        return [
            'categories' => ServiceCategory::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'taxes' => Tax::where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->orderBy('name')->get(),
            'vehicleSizes' => VehicleSize::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'vehicleTypes' => VehicleType::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $tenant->companyId()))
                ->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }
}
