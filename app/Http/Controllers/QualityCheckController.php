<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\QualityCheckActionRequest;
use App\Http\Requests\QualityCheckItemRequest;
use App\Http\Requests\QualityCheckRequest;
use App\Models\QualityCheck;
use App\Models\QualityChecklistTemplate;
use App\Models\WorkOrder;
use App\Services\AttachmentService;
use App\Services\QualityCheckDecisionService;
use App\Services\QualityCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityCheckController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->authorize('viewAny', QualityCheck::class);
        $branches = $tenant->accessibleBranches();

        return view('quality.index', [
            'waitingOrders' => WorkOrder::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $branches->pluck('id'))->where('status', 'awaiting_quality')
                ->with(['customer', 'vehicle'])->latest()->get(),
            'checks' => QualityCheck::where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $branches->pluck('id'))->with('workOrder')
                ->latest()->paginate(30),
        ]);
    }

    public function start(QualityCheckRequest $request, WorkOrder $workOrder, QualityCheckService $service): RedirectResponse
    {
        $this->authorize('create', [QualityCheck::class, $workOrder]);
        $template = $request->validated('template_id')
            ? QualityChecklistTemplate::findOrFail($request->validated('template_id'))
            : null;
        $check = $service->start($workOrder, $template);

        return redirect()->route('quality-checks.show', $check)->with('success', 'Quality round started.');
    }

    public function show(QualityCheck $qualityCheck): View
    {
        $this->authorize('view', $qualityCheck);

        return view('quality.show', [
            'check' => $qualityCheck->load(['items.workOrderService', 'attachments', 'workOrder.customer', 'workOrder.vehicle']),
        ]);
    }

    public function items(QualityCheckItemRequest $request, QualityCheck $qualityCheck, QualityCheckService $service): RedirectResponse
    {
        $this->authorize('perform', $qualityCheck);
        $service->updateItems($qualityCheck, $request->validated('items'));

        return back()->with('success', 'Quality results saved.');
    }

    public function action(
        QualityCheckActionRequest $request,
        QualityCheck $qualityCheck,
        string $action,
        QualityCheckDecisionService $service
    ): RedirectResponse {
        $this->authorize($action, $qualityCheck);
        if ($action === 'pass') {
            $service->pass($qualityCheck, $request->validated('notes'));
        } else {
            $service->fail($qualityCheck, $request->validated('reason'), $request->safe()->only([
                'reason_code', 'responsible_employee_id', 'required_action',
            ]));
        }

        return redirect()->route('quality-checks.show', $qualityCheck)->with('success', 'Quality decision recorded.');
    }

    public function photo(Request $request, QualityCheck $qualityCheck, AttachmentService $attachments): RedirectResponse
    {
        $this->authorize('perform', $qualityCheck);
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
            'category' => ['required', 'in:quality_overview,quality_failure,quality_pass'],
        ]);
        $attachments->store($qualityCheck, $request->file('file'), $request->input('category'));

        return back()->with('success', 'Private quality photo uploaded.');
    }
}
