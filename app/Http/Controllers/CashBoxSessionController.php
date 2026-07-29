<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CashBoxCountRequest;
use App\Http\Requests\CashBoxSessionActionRequest;
use App\Http\Requests\CashBoxSessionRequest;
use App\Http\Requests\CashOverShortActionRequest;
use App\Models\CashBox;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use App\Models\CashOverShortAdjustment;
use App\Services\CashBoxCountService;
use App\Services\CashBoxSessionService;
use App\Services\CashOverShortService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashBoxSessionController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.cash_sessions.view'), 403);

        return view('treasury.cash-sessions', [
            'sessions' => CashBoxSession::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->with(['cashBox', 'custodian', 'counts.lines', 'counts.adjustment'])->latest('id')->paginate(30),
            'cashBoxes' => CashBox::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))
                ->where('status', 'active')->get(),
            'custodians' => $tenant->company()->users()->where('status', 'active')->get(),
        ]);
    }

    public function store(CashBoxSessionRequest $request, CashBoxSessionService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_sessions.open'), 403);
        $service->open($request->validated());

        return back()->with('success', 'تم فتح جلسة الخزينة.');
    }

    public function action(
        CashBoxSessionActionRequest $request,
        CashBoxSession $cashBoxSession,
        string $action,
        CashBoxSessionService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_sessions.'.str_replace('start_counting', 'count', $action)), 403);
        $service->action($cashBoxSession, $action, $request->validated('notes'));

        return back()->with('success', 'تم تحديث جلسة الخزينة.');
    }

    public function count(
        CashBoxCountRequest $request,
        CashBoxSession $cashBoxSession,
        CashBoxCountService $service
    ): RedirectResponse {
        $this->authorize('count', $cashBoxSession);
        $service->create($cashBoxSession, $request->validated());

        return back()->with('success', 'تم تسجيل العد النقدي من السطور المحسوبة بالخادم.');
    }

    public function countAction(
        CashBoxSessionActionRequest $request,
        CashBoxCount $cashBoxCount,
        string $action,
        CashBoxCountService $service
    ): RedirectResponse {
        if ($action === 'approve') {
            $this->authorize('approve', $cashBoxCount);
        } elseif ($action === 'review') {
            $this->authorize('review', $cashBoxCount);
        } else {
            abort_unless($request->user()->hasPermission('treasury.cash_sessions.count'), 403);
        }
        $service->action($cashBoxCount, $action);

        return back()->with('success', 'تم تحديث دورة اعتماد العد.');
    }

    public function adjustment(
        CashOverShortActionRequest $request,
        CashBoxCount $cashBoxCount,
        CashOverShortService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_over_short.view'), 403);
        $service->create($cashBoxCount, $request->validated('description'));

        return back()->with('success', 'تم إنشاء فرق الخزينة للمراجعة.');
    }

    public function adjustmentAction(
        CashOverShortActionRequest $request,
        CashOverShortAdjustment $cashOverShortAdjustment,
        string $action,
        CashOverShortService $service
    ): RedirectResponse {
        $permission = $action === 'submit' ? 'view' : ($action === 'reverse' ? 'post' : $action);
        abort_unless($request->user()->hasPermission('treasury.cash_over_short.'.$permission), 403);
        $service->action($cashOverShortAdjustment, $action, $request->validated('reason'));

        return back()->with('success', 'تم تحديث فرق الخزينة.');
    }
}
