<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\WarrantyRequest;
use App\Http\Requests\WarrantyVoidRequest;
use App\Models\Warranty;
use App\Models\WorkOrder;
use App\Services\WarrantyIssuanceService;
use App\Services\WarrantyVoidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarrantyController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', Warranty::class);

        return view('warranties.index', [
            'warranties' => Warranty::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->with(['customer', 'vehicle'])->latest()->paginate(30),
        ]);
    }

    public function show(Warranty $warranty): View
    {
        $this->authorize('view', $warranty);

        return view('warranties.show', ['warranty' => $warranty->load(['customer', 'vehicle', 'items.service', 'claims'])]);
    }

    public function issue(WarrantyRequest $request, WarrantyIssuanceService $service): RedirectResponse
    {
        $this->authorize('issue', Warranty::class);
        $warranty = $service->issueForWorkOrder(WorkOrder::findOrFail($request->validated('work_order_id')));

        return $warranty
            ? redirect()->route('warranties.show', $warranty)->with('success', 'Warranty issued.')
            : back()->with('success', 'No eligible warranty service was found.');
    }

    public function print(Warranty $warranty): View
    {
        $this->authorize('print', $warranty);

        return view('warranties.print', ['warranty' => $warranty->load(['company', 'branch', 'customer', 'vehicle', 'items.service', 'items.product', 'items.roll'])]);
    }

    public function void(WarrantyVoidRequest $request, Warranty $warranty, WarrantyVoidService $service): RedirectResponse
    {
        $this->authorize('void', $warranty);
        $service->void($warranty, $request->validated('reason'));

        return back()->with('success', 'Warranty voided.');
    }
}
