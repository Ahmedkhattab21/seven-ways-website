<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BranchRequest;
use App\Http\Requests\BranchResponsibleUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchResponsibleUserService;
use App\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $branches = Branch::query()->withCount('accessibleUsers')->where('company_id', $tenant->companyId())
            ->when(! request()->user()->isCompanyAdministrator() && ! request()->user()->hasRole('system_admin'),
                fn ($query) => $query->whereIn('id', $tenant->accessibleBranches()->pluck('id')))
            ->orderByDesc('is_main')->orderBy('name')->paginate(20);

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        return view('branches.form', [
            'branch' => new Branch(),
            'responsibleCandidates' => collect(),
        ]);
    }

    public function show(Branch $branch, TenantContext $tenant): View
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $this->authorize('view', $branch);
        $branch->load(['company', 'settings', 'responsibleUser'])->loadCount('accessibleUsers');

        return view('branches.show', compact('branch'));
    }

    public function store(BranchRequest $request, TenantContext $tenant, BranchService $service): RedirectResponse
    {
        $service->create($tenant->companyId(), $this->data($request));

        return redirect()->route('branches.index')->with('status', 'تم إنشاء الفرع.');
    }

    public function edit(Branch $branch, TenantContext $tenant): View
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $this->authorize('update', $branch);

        return view('branches.form', [
            'branch' => $branch->load('responsibleUser'),
            'responsibleCandidates' => $this->responsibleCandidates($tenant),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch, TenantContext $tenant, BranchService $service): RedirectResponse
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $service->update($branch, $this->data($request));

        return redirect()->route('branches.index')->with('status', 'تم تحديث الفرع.');
    }

    public function disable(Branch $branch, TenantContext $tenant, BranchService $service): RedirectResponse
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $this->authorize('disable', $branch);
        $service->disable($branch);

        return back()->with('status', 'تم تعطيل الفرع.');
    }

    public function makeMain(Branch $branch, TenantContext $tenant, BranchService $service): RedirectResponse
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $this->authorize('update', $branch);
        $service->makeMain($branch);

        return back()->with('status', 'تم تعيين الفرع الرئيسي.');
    }

    public function assignResponsible(
        BranchResponsibleUserRequest $request,
        Branch $branch,
        TenantContext $tenant,
        BranchResponsibleUserService $service
    ): RedirectResponse {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $user = User::query()->where('company_id', $tenant->companyId())
            ->findOrFail($request->integer('responsible_user_id'));
        $service->assign($branch, $user);

        return back()->with('status', 'تم تعيين مسؤول تشغيل الفرع.');
    }

    private function data(BranchRequest $request): array
    {
        $data = [
            ...$request->safe()->except([
                'is_main', 'is_active', 'responsible_name', 'responsible_email',
                'responsible_password', 'responsible_password_confirmation', 'responsible_status',
            ]),
            'is_main' => $request->boolean('is_main'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];

        if (! $request->route('branch') && $request->filled('responsible_email')) {
            $data['responsible_account'] = [
                'name' => $request->string('responsible_name')->toString(),
                'email' => $request->string('responsible_email')->toString(),
                'password' => $request->string('responsible_password')->toString(),
                'status' => $request->input('responsible_status', 'active'),
            ];
        }

        return $data;
    }

    private function responsibleCandidates(TenantContext $tenant)
    {
        return User::query()
            ->where('company_id', $tenant->companyId())
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'branch_manager')->where('is_active', true))
            ->where(fn ($query) => $query->whereDoesntHave('responsibleBranch')
                ->orWhereHas('responsibleBranch', fn ($branch) => $branch->whereKey(request()->route('branch')?->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status', 'last_login_at']);
    }
}
