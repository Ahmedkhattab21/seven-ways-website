<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
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
        return view('branches.form', ['branch' => new Branch()]);
    }

    public function show(Branch $branch, TenantContext $tenant): View
    {
        abort_unless($branch->company_id === $tenant->companyId(), 403);
        $this->authorize('view', $branch);
        $branch->load(['company', 'settings'])->loadCount('accessibleUsers');

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

        return view('branches.form', compact('branch'));
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

    private function data(BranchRequest $request): array
    {
        return [
            ...$request->safe()->except(['is_main', 'is_active']),
            'is_main' => $request->boolean('is_main'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];
    }
}
