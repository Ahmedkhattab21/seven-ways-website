<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $warehouses = Warehouse::query()->where('company_id', $tenant->companyId())
            ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
            ->where('is_system', false)->with('branch')->latest()->paginate(20);

        return view('inventory.warehouses.index', compact('warehouses'));
    }

    public function create(TenantContext $tenant): View
    {
        return view('inventory.warehouses.form', ['warehouse' => new Warehouse, 'branches' => $tenant->accessibleBranches()]);
    }

    public function store(Request $request, WarehouseService $service): RedirectResponse
    {
        $service->create($this->validated($request));

        return redirect()->route('warehouses.index')->with('success', 'تم إنشاء المخزن.');
    }

    public function disable(Warehouse $warehouse, WarehouseService $service): RedirectResponse
    {
        $this->authorize('disable', $warehouse);
        $service->disable($warehouse);

        return back()->with('success', 'تم تعطيل المخزن.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:40', Rule::unique('warehouses')->where('branch_id', $request->integer('branch_id'))],
            'name' => ['required', 'string', 'max:255'],
            'warehouse_type' => ['required', Rule::in(['main', 'workshop', 'damaged', 'quarantine', 'other'])],
            'address' => ['nullable', 'string'], 'is_main' => ['boolean'],
            'allows_sale_issue' => ['boolean'], 'allows_work_order_issue' => ['boolean'], 'allows_damaged_stock' => ['boolean'],
        ]);
    }
}
