<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\EmployeeRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\EmployeeManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $employees = Employee::query()
            ->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->with(['branch', 'user'])
            ->withCount(['serviceSkills as active_skills_count' => fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($expiry) => $expiry->whereNull('certification_expires_at')->orWhereDate('certification_expires_at', '>=', today()))])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(fn ($inner) => $inner
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%"));
            })
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('employment_type'), fn ($query) => $query->where('employment_type', $request->input('employment_type')))
            ->when($request->input('has_skills') === 'yes', fn ($query) => $query->whereHas('serviceSkills', fn ($skills) => $skills->where('is_active', true)))
            ->when($request->input('has_skills') === 'no', fn ($query) => $query->whereDoesntHave('serviceSkills', fn ($skills) => $skills->where('is_active', true)))
            ->when($request->filled('service_id'), fn ($query) => $query->whereHas('serviceSkills', fn ($skills) => $skills
                ->where('service_id', $request->integer('service_id'))->where('is_active', true)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'branches' => $tenant->accessibleBranches(),
            'services' => Service::query()->where('company_id', $tenant->companyId())->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, TenantContext $tenant): View
    {
        $selectedBranchId = $this->resolveSelectedBranchId($request, $tenant);
        $options = $this->options($tenant, null, $selectedBranchId);
        $prefillServiceId = $this->resolveSelectedServiceId(
            $request,
            $tenant,
            $selectedBranchId,
            $options['services']
        );

        return view('employees.form', [
            'employee' => new Employee(),
            ...$options,
            'prefillServiceId' => $prefillServiceId,
            'prefillWarning' => $request->filled('service_id') && ! $prefillServiceId
                ? 'الخدمة المطلوبة غير موجودة أو غير متاحة في الفرع المحدد، لذلك لم تتم إضافتها.'
                : null,
            'returnUrl' => $this->internalReturnPath($request),
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeManagementService $service): RedirectResponse
    {
        $data = $request->validated();
        $hasSkill = ! empty($data['skills']);
        $employee = $service->save(new Employee(), $data);
        $message = $hasSkill
            ? 'تم إنشاء الفني وربط مهارات الخدمات بنجاح.'
            : 'تم إنشاء الموظف بنجاح.';

        return $this->redirectAfterSave($request, $employee)->with('status', $message);
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $employee->load(['branch', 'user', 'serviceSkills.service']);

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee, TenantContext $tenant): View
    {
        $this->authorize('update', $employee);
        $employee->load('serviceSkills');

        return view('employees.form', [
            'employee' => $employee,
            ...$this->options($tenant, $employee, $employee->branch_id),
            'prefillServiceId' => null,
            'prefillWarning' => null,
            'returnUrl' => $this->internalReturnPath(request()),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeManagementService $service): RedirectResponse
    {
        $service->save($employee, $request->validated());

        return $this->redirectAfterSave($request, $employee)->with('status', 'تم تحديث الموظف بنجاح.');
    }

    public function disable(Employee $employee, EmployeeManagementService $service): RedirectResponse
    {
        $this->authorize('disable', $employee);
        $service->disable($employee);

        return back()->with('status', 'تم تعطيل الموظف ومهاراته النشطة.');
    }

    private function options(TenantContext $tenant, ?Employee $employee, ?int $branchId): array
    {
        $branches = $tenant->accessibleBranches();
        $branchIds = $branches->pluck('id');
        $services = Service::query()
            ->where('company_id', $tenant->companyId())
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'branchServices' => fn ($query) => $query
                    ->whereIn('branch_id', $branchIds)->where('is_active', true)->where('is_available', true),
            ])
            ->whereHas('branchServices', fn ($query) => $query
                ->whereIn('branch_id', $branchIds)->where('is_active', true)->where('is_available', true))
            ->orderBy('name')->get();

        $linkedUserIds = Employee::withTrashed()
            ->where('company_id', $tenant->companyId())
            ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
            ->whereNotNull('user_id')->pluck('user_id');

        return [
            'branches' => $branches,
            'services' => $services,
            'users' => User::query()->where('company_id', $tenant->companyId())
                ->whereNotIn('id', $linkedUserIds)
                ->where('status', 'active')
                ->with('accessibleBranches:id')
                ->orderBy('name')->get(),
            'selectedBranchId' => $branchId ?: $tenant->branchId(),
            'employmentTypes' => [
                'full_time' => 'دوام كامل',
                'part_time' => 'دوام جزئي',
                'contract' => 'عقد',
                'temporary' => 'مؤقت',
                'intern' => 'متدرب',
            ],
            'skillLevels' => [
                'trainee' => 'متدرب',
                'junior' => 'مبتدئ',
                'intermediate' => 'متوسط',
                'senior' => 'متقدم',
                'expert' => 'خبير',
            ],
        ];
    }

    private function redirectAfterSave(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $returnUrl = $this->internalReturnPath($request);
        if ($returnUrl !== null) {
            return redirect()->to($returnUrl);
        }

        return redirect()->route('employees.show', $employee);
    }

    private function resolveSelectedBranchId(Request $request, TenantContext $tenant): ?int
    {
        if (! $request->filled('branch_id')) {
            return $tenant->branchId();
        }

        $branch = Branch::query()->find($request->integer('branch_id'));
        if (! $branch) {
            return null;
        }

        abort_unless(
            $branch->company_id === $tenant->companyId() && $tenant->user()?->canAccessBranch($branch),
            403,
            'الفرع المحدد خارج نطاق صلاحياتك.'
        );

        return $branch->id;
    }

    private function resolveSelectedServiceId(
        Request $request,
        TenantContext $tenant,
        ?int $branchId,
        $services
    ): ?int {
        if (! $request->filled('service_id')) {
            return null;
        }

        $service = Service::query()->find($request->integer('service_id'));
        if (! $service) {
            return null;
        }

        abort_unless(
            $service->company_id === $tenant->companyId(),
            403,
            'الخدمة المحددة خارج نطاق الشركة.'
        );

        if (! $branchId || ! $service->is_active) {
            return null;
        }

        $available = $services->firstWhere('id', $service->id)?->branchServices
            ->contains('branch_id', $branchId) ?? false;

        return $available ? $service->id : null;
    }

    private function internalReturnPath(Request $request): ?string
    {
        $candidate = trim((string) $request->input('return_url'));
        if ($candidate === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $candidate)) {
            return null;
        }

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return $candidate;
        }

        $target = parse_url($candidate);
        $origin = parse_url($request->getSchemeAndHttpHost());
        if (! is_array($target) || ! is_array($origin)
            || ! isset($target['scheme'], $target['host'])
            || strtolower($target['scheme']) !== strtolower($origin['scheme'] ?? '')
            || strtolower($target['host']) !== strtolower($origin['host'] ?? '')
            || $this->normalizedPort($target) !== $this->normalizedPort($origin)) {
            return null;
        }

        $path = $target['path'] ?? '/';

        return $path.(isset($target['query']) ? '?'.$target['query'] : '');
    }

    private function normalizedPort(array $parts): int
    {
        return (int) ($parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80));
    }
}
