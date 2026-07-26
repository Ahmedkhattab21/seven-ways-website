<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CostCenterMoveRequest;
use App\Http\Requests\CostCenterRequest;
use App\Models\CostCenter;
use App\Services\CostCenterHierarchyService;
use App\Services\CostCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CostCenterController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', CostCenter::class);

        return view('accounting.cost-centers.index', [
            'centers' => CostCenter::where('company_id', $tenant->companyId())->with(['branch', 'parent'])->orderBy('path')->get(),
            'branches' => $tenant->accessibleBranches(),
        ]);
    }

    public function store(CostCenterRequest $request, CostCenterService $service): RedirectResponse
    {
        $this->authorize('create', CostCenter::class);
        $service->save(new CostCenter, $request->validated());

        return back()->with('success', 'تم إنشاء مركز التكلفة.');
    }

    public function update(CostCenterRequest $request, CostCenter $costCenter, CostCenterService $service): RedirectResponse
    {
        $this->authorize('update', $costCenter);
        $service->save($costCenter, $request->validated());

        return back()->with('success', 'تم تحديث مركز التكلفة.');
    }

    public function move(CostCenterMoveRequest $request, CostCenter $costCenter, CostCenterHierarchyService $service): RedirectResponse
    {
        $this->authorize('move', $costCenter);
        $parent = $request->validated('parent_cost_center_id') ? CostCenter::findOrFail($request->validated('parent_cost_center_id')) : null;
        $service->move($costCenter, $parent);

        return back()->with('success', 'تم نقل مركز التكلفة.');
    }

    public function disable(CostCenter $costCenter, CostCenterService $service): RedirectResponse
    {
        $this->authorize('disable', $costCenter);
        $service->disable($costCenter);

        return back()->with('success', 'تم تعطيل مركز التكلفة.');
    }
}
