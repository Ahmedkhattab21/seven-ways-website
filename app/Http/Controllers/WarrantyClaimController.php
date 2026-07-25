<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\WarrantyClaimDecisionRequest;
use App\Http\Requests\WarrantyClaimInspectionRequest;
use App\Http\Requests\WarrantyClaimRequest;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\AttachmentService;
use App\Services\WarrantyClaimDecisionService;
use App\Services\WarrantyClaimInspectionService;
use App\Services\WarrantyClaimReworkService;
use App\Services\WarrantyClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarrantyClaimController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', WarrantyClaim::class);

        return view('warranty-claims.index', [
            'claims' => WarrantyClaim::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->with(['warranty', 'customer', 'vehicle'])->latest()->paginate(30),
        ]);
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', WarrantyClaim::class);

        return view('warranty-claims.form', [
            'warranties' => Warranty::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('status', 'active')->with('items.service')->get(),
        ]);
    }

    public function store(WarrantyClaimRequest $request, WarrantyClaimService $service): RedirectResponse
    {
        $claim = $service->create(
            Warranty::findOrFail($request->validated('warranty_id')),
            $request->safe()->except(['warranty_id', 'items']),
            $request->validated('items')
        );

        return redirect()->route('warranty-claims.show', $claim)->with('success', 'Warranty claim submitted.');
    }

    public function show(WarrantyClaim $warrantyClaim): View
    {
        $this->authorize('view', $warrantyClaim);

        return view('warranty-claims.show', [
            'claim' => $warrantyClaim->load(['warranty', 'customer', 'vehicle', 'items.warrantyItem.service', 'attachments', 'reworkOrders']),
        ]);
    }

    public function inspect(WarrantyClaimInspectionRequest $request, WarrantyClaim $warrantyClaim, WarrantyClaimInspectionService $service): RedirectResponse
    {
        $this->authorize('inspect', $warrantyClaim);
        $service->inspect($warrantyClaim, $request->validated('items'), $request->validated('notes'));

        return back()->with('success', 'Claim inspection completed.');
    }

    public function decide(WarrantyClaimDecisionRequest $request, WarrantyClaim $warrantyClaim, WarrantyClaimDecisionService $service): RedirectResponse
    {
        $this->authorize('decide', $warrantyClaim);
        $service->decide($warrantyClaim, $request->validated('decision'), $request->validated('items'), $request->validated('reason'));

        return back()->with('success', 'Claim decision recorded.');
    }

    public function rework(WarrantyClaim $warrantyClaim, WarrantyClaimReworkService $service): RedirectResponse
    {
        $this->authorize('approve', $warrantyClaim);
        $rework = $service->create($warrantyClaim);

        return redirect()->route('rework-orders.show', $rework)->with('success', 'Warranty rework created.');
    }

    public function resolve(WarrantyClaim $warrantyClaim, WarrantyClaimDecisionService $service): RedirectResponse
    {
        $this->authorize('resolve', $warrantyClaim);
        $service->resolve($warrantyClaim);

        return back()->with('success', 'Warranty claim resolved after final quality review.');
    }

    public function photo(Request $request, WarrantyClaim $warrantyClaim, AttachmentService $attachments): RedirectResponse
    {
        $this->authorize('inspect', $warrantyClaim);
        $request->validate(['file' => ['required', 'file', 'image', 'max:8192']]);
        $attachments->store($warrantyClaim, $request->file('file'), 'warranty_claim_photo');

        return back()->with('success', 'Private claim photo uploaded.');
    }
}
